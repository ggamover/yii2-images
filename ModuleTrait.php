<?php
/**
 * Created by PhpStorm.
 * User: kostanevazno
 * Date: 17.07.14
 * Time: 0:20
 */

namespace rico\yii2images;


use Yii;
use yii\base\Exception;

trait ModuleTrait
{
	/**
	 * @var null|Module
	 */
	private $_module;

	/**
	 * @return null|Module
	 * @throws Exception
	 */
	protected function getModule()
	{
		if ($this->_module == null) {
			$this->_module = Yii::$app->getModule('yii2images');
		}

		if(!$this->_module){
			throw new Exception("Yii2 images module not found, may be you didn't add it to your config?");
		}

		return $this->_module;
	}
}