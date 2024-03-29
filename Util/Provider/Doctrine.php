<?php

namespace Ali\DataLoaderBundle\Util\Provider;

class Doctrine extends Base implements ModelInterface
{

    /** @var \Doctrine\ORM\EntityManager */
    protected $_em;

    /** @var string */
    protected $_data_dir;

    /** @var array */
    protected $_fixtures = array();

    /** @var array */
    protected $_persisted = array();

    /**
     * load defaut data (fixtures) into the database
     * 
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
                        $entity = $this->_assertNotExists($model, $items);
                        if (is_null($entity))
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
     * @return  entity 
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
                    if (isset($this->_persisted[$assoc_metadata['targetEntity']][$value]))
                    {
                        $assoc_entity = $this->_persisted[$assoc_metadata['targetEntity']][$value];
                    }
                    else
                    {
                        $_assoc_entity = $this->_assertNotExists(
                                $assoc_metadata['targetEntity'], $this->_fixtures[$assoc_metadata['targetEntity']][$value]);
                        if (is_null($_assoc_entity))
                        {
                            $assoc_entity = $this->_persistEntity($assoc_metadata['targetEntity'], $value, $this->_fixtures[$assoc_metadata['targetEntity']][$value]);
                        }
                        else
                        {
                            $assoc_entity = $_assoc_entity;
                        }
                    }
                    $assoc_identifier = $assoc_entity->getId();
                    $ref = $em->getReference(get_class($assoc_entity), $assoc_identifier);
                    $entity->$method($ref);
                }
                else
                {
                    throw new \Exception("Cannot find data for association [{$attribute}]: check the appropriate index in your data files. ");
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
     * and return found object
     * 
     * @param string $model
     * @param array  $items
     * 
     * @return object
     */
    protected function _assertNotExists($model, array $items)
    {
        if ($this->_allow_duplication == TRUE)
        {
            return NULL;
        }
        $df = $this->_duplication_fields;
        $em = $this->_em;
        $metadata = $em->getMetadataFactory()->getMetadataFor($model);
        $dql = "select x from {$model} x where ";
        $where = array();
        if (isset ($df[$model]))
        {
            foreach ($df[$model] as $attr)
            {
                $where[] = " x.{$attr} = '{$items[$attr]}' ";
            }
        }
        else
        {
            foreach ($items as $attribute => $value)
            {
                if ($metadata->hasAssociation($attribute) == TRUE)
                {
                    $assoc_metadata = $metadata->getAssociationMapping($attribute);
                    $metadata_target_entity = $em->getMetadataFactory()->getMetadataFor($assoc_metadata["targetEntity"]);
                    $identifier_getter = "get" . ucfirst(current($metadata_target_entity->getIdentifier()));
                    if (isset($this->_persisted[$assoc_metadata["targetEntity"]][$value]))
                    {
                        if (count($metadata_target_entity->getIdentifier()) > 1)
                        {
                            throw new Exception(' data loader do not support association with mixed identifier');
                        }
                        $value = $this->_persisted[$assoc_metadata["targetEntity"]][$value]->$identifier_getter();
                    }
                    else
                    {
                        return $this->_assertNotExists($assoc_metadata["targetEntity"], $this->_fixtures[$assoc_metadata['targetEntity']][$value]);
                    }
                }
                $where[] = " x.{$attribute} = '{$value}' ";
            }
        }
        $dql .= implode(' and ', $where);
        $data = $em->createQuery($dql)->getResult();
        return empty($data) ? NULL : $data[0] ;
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
                if (( is_array($array_element) || is_object($array_element) ) && $this->_inArray($elem, $array_element))
                {
                    return TRUE;
                    exit;
                }
            }
        }
        return FALSE;
    }

    /**
     * get doctrine entity manager
     * 
     * @return \Doctrine\ORM\EntityManager
     */
    public function getEntityManager()
    {
        return $this->_em;
    }

    /**
     * set doctrine entity manager
     * 
     * @param \Doctrine\Common\Persistence\ObjectManager $_em 
     * 
     * @return void
     */
    public function setEntityManager(\Doctrine\Common\Persistence\ObjectManager $_em)
    {
        $this->_em = $_em;
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

}
