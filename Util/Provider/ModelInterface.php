<?php

namespace Ali\DataLoaderBundle\Util\Provider;

interface ModelInterface
{
    function getEntityManager();

    function setEntityManager($_em);

    function load($data_folder);
}
