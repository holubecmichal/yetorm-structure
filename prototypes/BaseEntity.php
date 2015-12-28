<?php

namespace App\Model\Entity;
use YetORM\Entity;
use YetORM\EntityCollection;
use YetORM\Reflection\MethodProperty;

/**
 * Základní třída entity.
 *
 * @filesource	BaseEntity.php
 * @author		© Web Data Studio, www.web-data.cz
 * @contributor Michal Holubec
 * @version		1.0.1
 */
abstract class BaseEntity extends Entity
{

	/** @return array */
	function toArray()
	{
		$ref = static::getReflection();
		$values = array();

		foreach ($ref->getEntityProperties() as $name => $property) {

			if ($property instanceof MethodProperty) {
				$value = $this->{'get' . $name}();
			} else {
				$value = $this->$name;
			}

			if (!($value instanceof EntityCollection || $value instanceof Entity)) {
				$values[$name] = $value;
			}
		}

		return $values;
	}

}
