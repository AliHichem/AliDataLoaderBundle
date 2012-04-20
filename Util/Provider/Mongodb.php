<?php

namespace Ali\DataLoaderBundle\Util\Provider;

class Mongodb extends Base implements ModelInterface
{

    /** @var \Doctrine\ODM\MongoDB\DocumentManager */
    protected $_em;

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
        return $em->getRepository($model)->findBy($items)->count() > 0
                ? FALSE
                : TRUE;
    }

    /**
     * load data from each yml file in the indicated folder
     * 
     * @param string $data_folder
     * 
     * @return void
     */
    public function load($data_folder)
    {
        return parent::load($data_folder);
    }

    /**
     * get entity manager
     * 
     * @return type 
     */
    public function getEntityManager()
    {
        return parent::getEntityManager();
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

}