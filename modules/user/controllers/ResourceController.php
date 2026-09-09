<?php
declare(strict_types=1);
namespace app\modules\user\controllers;

abstract class ResourceController extends ApiController
{
    protected function verbs(): array
    {
        return [
            'index' => ['GET'], 'view' => ['GET'], 'create' => ['POST'],
            'update' => ['PUT', 'PATCH'], 'delete' => ['DELETE'], 'options' => ['OPTIONS'],
        ];
    }

    public function actionIndex(): never { $this->pending(); }
    public function actionView(int $id): never { $this->pending(); }
    public function actionCreate(): never { $this->pending(); }
    public function actionUpdate(int $id): never { $this->pending(); }
    public function actionDelete(int $id): never { $this->pending(); }
}
