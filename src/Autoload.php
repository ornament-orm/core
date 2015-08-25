<?php

namespace Ornament;

use ReflectionClass;

trait Autoload
{
    /**
     * @onLoad
     */
    public function autoload(array $annotations)
    {
        foreach ($annotations as $property => $anns) {
            if (!isset($this->$property)) {
                continue;
            }
            if (isset($anns['Model'])) {
                $class = $anns['Model'];
                if (isset($anns['Mapping'])) {
                    $maps = $anns['Mapping'];
                } else {
                    $maps = ['id' => $property];
                }
                $ctorargs = [];
                if (isset($anns['Constructor'])) {
                    foreach ($anns['Constructor'] as $arg) {
                        $ctorargs[$arg] = $this->$arg;
                    }
                }
                $ref = new ReflectionClass($class);
                $model = $ref->newInstanceArgs($ctorargs);
                foreach ($maps as $field => $mapto) {
                    $model->$field = $this->$mapto;
                }
                $model->load();
                $this->$mapto = $model;
            }
        }
    }
}

