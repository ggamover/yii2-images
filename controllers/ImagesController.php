<?php
/**
 * Created by PhpStorm.
 * User: costa
 * Date: 25.06.14
 * Time: 15:35
 */

namespace rico\yii2images\controllers;

use yii\web\Controller;
use yii;
use rico\yii2images\models\Image;
use \rico\yii2images\ModuleTrait;
use yii\web\HttpException;

class ImagesController extends Controller
{
	use ModuleTrait;

	/**
	 *
	 * All we need is love. No.
	 * We need item (by id or another property) and alias (or images number)
	 * @param string $item
	 * @param $dirtyAlias
	 * @return yii\console\Response|yii\web\Response
	 * @throws \ImagickException
	 * @throws yii\base\Exception
	 * @throws HttpException
	 */
	public function actionImageByItemAndAlias($dirtyAlias, string $item='')
	{
		$dotParts = explode('.', $dirtyAlias);
		if(!isset($dotParts[1])){
			throw new HttpException(404, 'Image must have extension');
		}
		$dirtyAlias = $dotParts[0];

		$size = explode('_', $dirtyAlias)[1] ?? false;
		$alias = explode('_', $dirtyAlias)[0] ?? false;
		$image = $this->getModule()->getImage($item, $alias);

		if($image->getExtension() != $dotParts[1]){
			throw new HttpException(404, 'Image not found (extension)');
		}

		if($image){
			$response = \Yii::$app->response;
			$response->format = yii\web\Response::FORMAT_RAW;
			$response->headers->add('Content-Type', $image->getMimeType($size));
			$response->data = $image->getContent($size);
			return $response;
		}else{
			throw new HttpException(404, 'There is no images');
		}

	}
}
