<?php
declare(strict_types=1);
namespace app\modules\user\controllers;

use app\models\Profile;
use Yii;
use yii\helpers\FileHelper;
use yii\web\BadRequestHttpException;
use yii\web\UploadedFile;

final class ProfileController extends ApiController
{
    protected function verbs(): array
    {
        return ['index' => ['GET', 'POST'], 'options' => ['OPTIONS']];
    }

    public function actionIndex(): array
    {
        $userId = (int) $this->module->get('user')->id;
        $profile = Profile::findOne(['user_id' => $userId]);
        if ($profile === null) {
            $profile = new Profile();
            $profile->user_id = $userId;
            $profile->first_name = '';
            $profile->last_name = '';
        }
        if (Yii::$app->request->isPost) {
            $body = Yii::$app->request->getBodyParams();
            foreach (['first_name', 'last_name'] as $field) {
                if (!array_key_exists($field, $body)) {
                    continue;
                }
                if (!is_string($body[$field]) || mb_strlen(trim($body[$field])) > 100) {
                    throw new BadRequestHttpException('Имя и фамилия должны быть строками до 100 символов.');
                }
                $profile->$field = trim($body[$field]);
            }
            $photo = UploadedFile::getInstanceByName('photo');
            $newPath = null;
            $oldPhoto = $profile->photo;
            if ($photo !== null) {
                $form = new \yii\base\DynamicModel(['photo' => $photo]);
                $form->addRule('photo', 'image', [
                    'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
                    'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                    'maxSize' => 5 * 1024 * 1024,
                    'maxWidth' => 4096, 'maxHeight' => 4096,
                ]);
                if (!$form->validate()) {
                    throw new BadRequestHttpException('Загрузите изображение JPG, PNG или WebP до 5 МБ и 4096 × 4096 пикселей.');
                }
                $extension = match (FileHelper::getMimeType($photo->tempName)) {
                    'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
                    default => throw new BadRequestHttpException('Неподдерживаемое изображение.'),
                };
                $directory = Yii::getAlias('@webroot/uploads/avatars');
                FileHelper::createDirectory($directory);
                $profile->photo = 'uploads/avatars/' . bin2hex(random_bytes(24)) . '.' . $extension;
                $newPath = Yii::getAlias('@webroot/' . $profile->photo);
                if (!$photo->saveAs($newPath)) {
                    throw new \yii\web\ServerErrorHttpException('Не удалось сохранить фото.');
                }
            }
            try {
                if (!$profile->save(false)) {
                    throw new \yii\web\ServerErrorHttpException('Не удалось сохранить профиль.');
                }
            } catch (\Throwable $error) {
                if ($newPath !== null) {
                    @unlink($newPath);
                }
                throw $error;
            }
            if ($newPath !== null && is_string($oldPhoto)
                && preg_match('~^uploads/avatars/[a-f0-9]{48}\.(jpg|png|webp)$~D', $oldPhoto)) {
                @unlink(Yii::getAlias('@webroot/' . $oldPhoto));
            }
        }
        return [
            'username' => $this->module->get('user')->identity->username,
            'first_name' => $profile->first_name,
            'last_name' => $profile->last_name,
            'photo_url' => $profile->photo === null ? null
                : Yii::$app->request->hostInfo . Yii::$app->request->baseUrl . '/' . $profile->photo,
        ];
    }
}
