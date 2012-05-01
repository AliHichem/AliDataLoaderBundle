<?php

namespace Ali\DataLoaderBundle\Util\Provider;

class Base
{

    protected $_em;
    protected $_data_dir;
    protected $_fixtures = array();
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
     * @return  entity 
     */
    protected function _persistEntity($model, $index, array $items)
    {

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

    /**
     * get entity manager
     * 
     * @return type 
     */
    public function getEntityManager()
    {
        return $this->_em;
    }

    /**
     * set entity manager
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