<?php
declare(strict_types=1);

// Standalone integration checks: no Yii, database or test runner required.
require_once __DIR__ . '/../core/Bootstrap.php';
Bootstrap::boot(__DIR__ . '/../config/applications.php');

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function input(string $name, array $items): Action\CalculateQuote\InputDTO
{
    $dto = new Action\CalculateQuote\InputDTO();
    $dto->loadFromArray(['buyer' => $name, 'products' => $items], ['buyer' => 'customer', 'products' => 'items']);
    return $dto;
}
function emptyContext(): void
{
    try {
        ApplicationManager::getCurrentApplication();
    } catch (LogicException) {
        return;
    }
    throw new RuntimeException('Application context leaked.');
}
// Models operate on their own data without application context or adapters.
$product = new Service\Catalog\Model\Product('Tea', 25000, 8);
$product->setName('Club tea');
$product->setPrice(20000);
$product->setStock(5);
check($product->getName() === 'Club tea' && $product->getPrice() === 20000 && $product->getStock() === 5, 'Product data access.');
check($product->toArray() === ['name' => 'Club tea', 'price' => 20000, 'stock' => 5], 'Product serialization.');
$basket = new Service\Order\Model\Basket();
check($basket->isEmpty(), 'New basket is empty.');
$basket->setLine('tea', 'Tea', 2, 20000);
$basket->setLine('tea', 'Tea', 3, 20000);
check(count($basket->getLines()) === 1 && $basket->getLines()[0]['total'] === 60000, 'Replacing a line updates its total.');
$basket->removeLine('tea');
check($basket->isEmpty(), 'Removing the last line empties the basket.');
emptyContext();
$shop = ApplicationManager::get('shop');
$club = ApplicationManager::get('club');
check($shop instanceof Application\Shop && $club instanceof Application\Club, 'Both applications must start.');
check($shop->catalog()['tea']['price'] === 25000, 'Shop catalog price.');
check($club->catalog()['tea']['price'] === 20000, 'Club adapter must use its own catalog.');
$dto = input('  Alice  ', ['tea' => 2, 'sandwich' => 2, 'blanket' => 1]);
$regular = $shop->quote($dto);
$member = $club->quote($dto);
check($regular['total'] === 176000 && $regular['discount'] === 0, 'Shop total must be 1760 rubles.');
check($member['subtotal'] === 166000 && $member['discount'] === 16600 && $member['total'] === 149400, 'Club total must be 1494 rubles.');
check($regular['customer'] === 'Alice', 'Customer name normalization.');
check(count($regular['lines']) === 3, 'Three basket lines.');
check($shop->quote($dto) === $regular, 'Club call must not change shop state.');
emptyContext();
$cases = [
    input('', ['tea' => 1]),
    input('Alice', []),
    input('Alice', ['unknown' => 1]),
    input('Alice', ['blanket' => 4]),
    input('Alice', ['tea' => -1]),
    input('Alice', ['tea' => '2']),
];
foreach ($cases as $invalid) {
    try {
        $shop->quote($invalid);
        throw new RuntimeException('Expected a domain error.');
    } catch (DomainException) {
        emptyContext();
    }
}
// Simulate a nested application invocation and verify the outer context survives.
ApplicationManager::pushApplicationCall('shop');
check($club->quote($dto)['total'] === 149400, 'Nested club call.');
check(ApplicationManager::getCurrentApplication() === $shop, 'Outer context must be restored.');
try {
    $club->quote(input('Alice', ['blanket' => 4]));
} catch (DomainException) {
    check(ApplicationManager::getCurrentApplication() === $shop, 'Outer context must survive an exception.');
}
ApplicationManager::popApplicationCall('shop');
emptyContext();
$totals = new Service\Order\Service\Totals();
check($totals->calculate([['total' => 105]], 10)['discount'] === 11, 'Kopeck rounding.');
try {
    $totals->calculate([], 101);
    throw new RuntimeException('Expected invalid discount.');
} catch (DomainException) {}
Bootstrap::boot('this-file-must-not-be-loaded.php');
check(ApplicationManager::get('shop') === $shop, 'Bootstrap must not recreate applications.');
echo "Demo checks passed: applications, DTO, models, adapters, totals, validation and context.\n";
