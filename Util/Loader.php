<?php

namespace Ali\DoctrineDataLoaderBundle\Util;

class Loader
{

    /** @var Doctrine\ORM\EntityManager */
    private $_em;
    protected $_data_dir;
    protected $_fixtures = array();
    protected $_persisted = array();

    public function __construct(\Doctrine\ORM\EntityManager $em)
    {
        $this->_em = $em;
    }

    /**
     * load data from folder
     * 
     * @param string $data_folder 
     * 
     * @return void
     */
    public function load($data_folder)
    {
        $this->_data_dir = $data_folder;
        $this->_prepareFixtures();
        $this->_loadDefaultData();
    }

    /**
     * load defaut data (fixtures) into the database
     * 
     * @throws ExcelException
     * @param string $data_path 
     * 
     * @return void
     */
    protected function _loadDefaultData()
    {
        $data_path = $this->_data_dir;
        $em = $this->_em;
        if (is_dir($data_path))
        {
            try
            {
                foreach ($this->_fixtures as $model => $values)
                {
                    foreach ($values as $index => $items)
                    {
                        if ($this->_assertNotExists($model, $items))
                        {
                            $this->_persistEntity($model, $index, $items);
                        }
                    }
                }
            }
            catch (Exception $e)
            {
                throw $e;
            }
        }
    }

    /**
     * persist and flush entity
     * 
     * @param string $model
     * @param string $index
     * @param array $items
     * 
     * @return doctrine entity 
     */
    protected function _persistEntity($model, $index, array $items)
    {
        $em = $this->_em;
        $entity = new $model();
        $metadata = $em->getMetadataFactory()->getMetadataFor($model);
        foreach ($items as $attribute => $value)
        {
            $method = $this->_getMethodFromAttribute($attribute, 'set');
            if ($metadata->hasAssociation($attribute) == TRUE)
            {
                $assoc_metadata = $metadata->getAssociationMapping($attribute);
                if (isset($this->_fixtures[$assoc_metadata['targetEntity']][$value]))
                {
                    $assoc_entity = $this->_persistEntity($assoc_metadata['targetEntity'],
                                                          $value,
                                                          $this->_fixtures[$assoc_metadata['targetEntity']][$value]);
                    $assoc_identifier = $assoc_entity->getId();
                    $ref = $em->getReference('SupercolegioMainAppBundle:Area',
                                             (int) $assoc_identifier);
                    $entity->$method($ref);
                }
                else
                {
                    throw new Exception("Cannot find data for association [{$attribute}] ");
                }
            }
            else
            {
                $entity->$method($value);
            }
        }
        if (!isset($this->_persisted[$model][$index]))
        {
            $em->persist($entity);
            $this->_persisted[$model][$index] = $entity;
        }
        $em->flush();
        $em->detach($entity);
        return $entity;
    }

    /**
     * parse data files and load data
     * 
     * @return void
     */
    protected function _prepareFixtures()
    {
        $data_path = $this->_data_dir;
        foreach (glob($data_path . '/*.yml') as $file)
        {
            $yaml = new \Symfony\Component\Yaml\Parser();
            $handle = fopen($file, "r");
            $index = $model = str_replace('#', '', trim(fgets($handle)));
            $this->_fixtures[$index] = array();
            $content = file_get_contents($file);
            $values = $yaml->parse($content);
            if (!$this->_inArray($values, $this->_fixtures[$index]))
            {
                $this->_fixtures[$index] = $values;
            }
        }
    }

    /**
     * check if record already exists in the database
     * 
     * @param string $model
     * @param array  $items
     * 
     * @return boolean
     */
    protected function _assertNotExists($model, array $items)
    {
        $em = $this->_em;
        $metadata = $em->getMetadataFactory()->getMetadataFor($model);
        $dql = "select count(x) from {$model} x where ";
        $where = array();
        foreach ($items as $attribute => $value)
        {
            if ($metadata->hasAssociation($attribute) == TRUE)
            {
                $assoc_metadata = $metadata->getAssociationMapping($attribute);
                $metadata_target_entity = $em->getMetadataFactory()->getMetadataFor($assoc_metadata["targetEntity"]);
                if (isset($this->_persisted[$assoc_metadata["targetEntity"]][$value]))
                {
                    if (count($metadata_target_entity->getIdentifier()) > 1)
                    {
                        throw new Exception('Doctrine data loader do not support association with mixed identifier');
                    }
                    $identifier_getter = "get".ucfirst(current($metadata_target_entity->getIdentifier()));
                    $value = $this->_persisted[$assoc_metadata["targetEntity"]][$value]->$identifier_getter();
                }
                else
                {
                    return TRUE;
                }
            }
            $where[] = " x.{$attribute} = '{$value}' ";
        }
        $dql .= implode(' and ', $where);
        return $em->createQuery($dql)->getSingleScalarResult() > 0
                ? FALSE
                : TRUE;
    }

    /**
     * get method name from attribute
     * 
     * @param string $attribute
     * @param string $prefix
     * 
     * @return string
     */
    protected function _getMethodFromAttribute($attribute, $prefix)
    {
        $method = '';
        $stack = explode('_', $attribute);
        foreach ($stack as $partial)
        {
            $method .= ucfirst($partial);
        }
        return "{$prefix}{$method}";
    }

    /**
     *
     * @param mixed $elem
     * @param array $array
     * 
     * @return boolean 
     */
    protected function _inArray($elem, array $array)
    {
        if (is_array($array) || is_object($array))
        {
            if (is_object($array))
            {
                $temp_array = get_object_vars($array);
                if (in_array($elem, $temp_array))
                {
                    return TRUE;
                }
            }
            if (is_array($array) && in_array($elem, $array))
            {
                return TRUE;
            }
            foreach ($array as $array_element)
            {
                if (( is_array($array_element) || is_object($array_element) ) && $this->_inArray($elem,
                                                                                                 $array_element))
                {
                    return TRUE;
                    exit;
                }
            }
        }
        return FALSE;
    }

}