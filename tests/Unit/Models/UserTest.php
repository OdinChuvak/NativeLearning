<?php

declare(strict_types=1);

namespace app\tests\Unit\Models;

use app\models\User;

final class UserTest extends \Codeception\Test\Unit
{
    public function _fixtures(): array
    {
        return ['users' => \app\tests\Support\UserFixture::class];
    }
    public function testFindUserById()
    {
        /** @var User $user */
        $user = User::findIdentity(100);

        verify($user)->notEmpty();
        verify($user->username)->equals('admin');
        verify(User::findIdentity(999))->empty();
    }

    public function testFindUserByAccessToken()
    {
        /** @var User $user */
        $user = User::findIdentityByAccessToken('100-token');

        verify($user)->empty();
        verify(User::findIdentityByAccessToken('non-existing'))->empty();
    }

    public function testFindUserByUsername()
    {
        /** @var User $user */
        $user = User::findByUsername('admin');

        verify($user)->notEmpty();
        verify(User::findByUsername('not-admin'))->empty();
    }

    /**
     * @depends testFindUserByUsername
     */
    public function testValidateUser()
    {
        /** @var User $user */
        $user = User::findByUsername('admin');

        self::assertNull($user->getAuthKey());
        verify($user->validateAuthKey('test100key'))->false();
        verify($user->validateAuthKey('test102key'))->empty();
    }
}
