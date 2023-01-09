<?php

namespace rico\yii2images\controllers;

use yii\web\Controller;

/**
 * DefaultController
 * \rico\yii2images\controllers\DefaultController
 */
class DefaultController extends Controller
{
	/**
	 * @return string
	 */
	public function actionIndex()
	{
		return $this->render('index');
	}
}
