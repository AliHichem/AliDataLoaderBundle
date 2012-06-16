<?php

namespace Ali\DataLoaderBundle\Util\Factory;

use Doctrine\Common\Persistence\ObjectManager;

abstract class Loader
{

    protected $_provider;
    protected $_allow_duplication = FALSE;

    /**
     * enable/disable duplication and set duplication's fields  to check on
     * 
     * @param boolean   $value
     * @param array     $fields
     * 
     * @return Loader 
     */
    public function duplication($value = TRUE, $fields = array())
    {
        $this->_provider->duplication($value, $fields);
        return $this;
    }

    /**
     * set the model driver 
     *  supported driver : doctrine, mongodb
     * 
     * @param string            $driver
     * @param ObjectManager     $em 
     * 
     * @return void
     */
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
        return $this;
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
