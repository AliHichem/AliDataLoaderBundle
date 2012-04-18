<?php

namespace Ali\DataLoaderBundle\Util\Factory;

abstract class Loader
{

    protected $_provider;

    public function setDriver($driver, $em = NULL)
    {
        $class_name = ucfirst(strtolower($driver));
        $class = "\\Ali\\DataLoaderBundle\\Util\\Provider\\{$class_name}";
        
        if (!class_exists($class))
        {
            throw new \Ali\DataLoaderBundle\Util\Exception("Cannot find driver for [{$driver}] - {$class}");
        }
        
        if (in_array($driver, array('doctrine', 'mongodb', 'couchdb')))
        {
            $this->_provider = new $class();
            $this->_provider->setEntityManager($em);
        }
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
        $this->_provider->load($data_folder);
    }

}