<?php

/**
 *  _   __ __ _____ _____ ___  ____  _____
 * | | / // // ___//_  _//   ||  __||_   _|
 * | |/ // /(__  )  / / / /| || |     | |
 * |___//_//____/  /_/ /_/ |_||_|     |_|
 * @link https://vistart.me/
 * @copyright Copyright (c) 2016 - 2017 vistart
 * @license https://vistart.me/license/
 */

namespace rhosocial\organization\tests\org;

use rhosocial\organization\rbac\permissions\SetUpOrganization;
use rhosocial\organization\tests\data\ar\org\SubordinateLimit;
use rhosocial\organization\tests\data\ar\user\User;
use rhosocial\organization\tests\data\ar\org\Organization;
use rhosocial\organization\tests\TestCase;
use Yii;
use yii\base\InvalidParamException;

/**
 * Class DepartmentLimitTest
 * @package rhosocial\organization\tests\org
 * @version 1.0
 * @author vistart <i@vistart.me>
 */
class DepartmentLimitTest extends TestCase
{
    /**
     * @var User
     */
    protected $user;
    /**
     * @var Organization
     */
    protected $organization;
    /**
     * @var Organization
     */
    protected $department;
    protected function setUp()
    {
        parent::setUp(); // TODO: Change the autogenerated stub
        $this->user = new User(['password' => '123456']);
        $this->assertTrue($this->user->register([$this->user->createProfile(['nickname' => 'vistart'])]));
        $this->assertNotNull(Yii::$app->authManager->assign((new SetUpOrganization)->name, $this->user));
        $this->assertTrue($this->user->setUpOrganization($this->faker->name));
        $this->organization = $this->user->lastSetUpOrganization;
        $this->assertInstanceOf(Organization::class, $this->organization);
    }
    protected function tearDown()
    {
        if ($this->organization) {
            $this->assertTrue($this->user->revokeOrganization($this->organization));
        }
        Organization::deleteAll();
        if ($this->user) {
            $this->assertTrue($this->user->deregister());
        }
        User::deleteAll();
        parent::tearDown(); // TODO: Change the autogenerated stub
    }

    /**
     * @group department
     * @group setup
     * @group limit
     */
    public function testNormal()
    {
        $limit = SubordinateLimit::find()->createdBy($this->organization)->one();
        $this->assertNull($limit);

        $limit = SubordinateLimit::getLimit($this->organization);
        $noInit = SubordinateLimit::buildNoInitModel();
        $this->assertEquals($noInit->defaultLimit, $limit);

        $limit = SubordinateLimit::find()->createdBy($this->organization)->one();
        /* @var $limit SubordinateLimit */
        $this->assertInstanceOf(SubordinateLimit::class, $limit);
        $this->assertEquals($noInit->defaultLimit, $limit->limit);
        $this->assertEquals($this->organization->subordinateLimit->defaultLimit, $this->organization->subordinateLimit->limit);
        $this->assertFalse($this->organization->hasReachedSubordinateLimit());

        $this->assertTrue($this->user->setUpDepartment($this->faker->name, $this->organization));
        $this->department = $this->user->lastSetUpOrganization;
        $this->assertEquals(1, (int)$this->user->getCreatorsAtOrganizations()->andWhere(['type' => Organization::TYPE_DEPARTMENT])->count());
        $limit = SubordinateLimit::getLimit($this->organization);
        $this->assertEquals($noInit->defaultLimit, $limit);
        $this->assertFalse($this->organization->hasReachedSubordinateLimit());
    }

    /**
     * @group department
     * @group setup
     * @group limit
     */
    public function testLimit()
    {
        for ($i = 0; $i < 50; $i++) {
            $this->assertTrue($this->user->setUpDepartment($this->faker->name, $this->organization));
        }
        $this->assertEquals(50, SubordinateLimit::getLimit($this->organization));
        $this->assertEquals($this->organization->subordinateLimit->defaultLimit, $this->organization->subordinateLimit->limit);
        try {
            $this->user->setUpDepartment($this->faker->name, $this->organization);
        } catch (InvalidParamException $ex) {
            $this->assertEquals("You do not have permission to set up department.", $ex->getMessage());
        }
    }
}
