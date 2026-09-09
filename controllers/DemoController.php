<?php

declare(strict_types=1);

namespace app\controllers;

use Action\CalculateQuote\InputDTO;
use yii\web\BadRequestHttpException;
use yii\web\Controller;

final class DemoController extends Controller
{
    public function actionIndex(): string
    {
        $request = \Yii::$app->request;
        $applicationId = $request->get('application', 'shop');
        if (!is_string($applicationId) || !in_array($applicationId, ['shop', 'club'], true)) {
            throw new BadRequestHttpException('Неизвестное приложение.');
        }
        /** @var \Application\Shop|\Application\Club $application */
        $application = \ApplicationManager::get($applicationId);
        $catalog = $application->catalog();
        $customer = 'Гость';
        $quantities = ['tea' => 2, 'sandwich' => 2, 'blanket' => 1];
        $quote = null;
        $error = null;

        if ($request->isPost) {
            try {
                $customer = $request->post('customer', '');
                $quantities = $request->post('items', []);
                if (!is_string($customer) || !is_array($quantities)) {
                    throw new \DomainException('Некорректный формат данных формы.');
                }
                $items = [];
                foreach ($quantities as $code => $value) {
                    if (!is_string($code) || !array_key_exists($code, $catalog)
                        || !is_scalar($value) || is_bool($value)) {
                        throw new \DomainException('Некорректный товар или количество.');
                    }
                    $quantity = filter_var($value, FILTER_VALIDATE_INT);
                    if ($quantity === false || $quantity < 0 || $quantity > 100) {
                        throw new \DomainException('Количество должно быть целым числом от 0 до 100.');
                    }
                    if ($quantity > 0) {
                        $items[$code] = $quantity;
                    }
                }
                $input = new InputDTO();
                $input->loadFromArray(
                    ['buyer_name' => $customer, 'basket' => $items],
                    ['buyer_name' => 'customer', 'basket' => 'items'],
                );
                $quote = $application->quote($input);
            } catch (\DomainException $exception) {
                $error = $exception->getMessage();
            }
        }

        return $this->render('index', [
            'applicationId' => $applicationId,
            'catalog' => $catalog,
            'customer' => is_string($customer) ? $customer : '',
            'quantities' => is_array($quantities) ? $quantities : [],
            'quote' => $quote,
            'error' => $error,
        ]);
    }
}
