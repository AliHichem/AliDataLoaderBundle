<?php

namespace Ali\DataLoaderBundle\Util\Provider;

interface ModelInterface
{
    function getEntityManager();

    function setEntityManager(\Doctrine\Common\Persistence\ObjectManager $_em);

    function load($data_folder);
}
