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
use rhosocial\organization\tests\data\ar\org\Organization;
use rhosocial\organization\tests\data\ar\user\User;
use rhosocial\organization\tests\TestCase;
use Yii;
use yii\db\IntegrityException;

/**
 * Class OrganizationMemberRestrictTest
 * @package rhosocial\organization\tests\org
 * @version 1.0
 * @author vistart <i@vistart.me>
 */
class OrganizationMemberRestrictTest extends TestCase
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
     * @var User[]
     */
    protected $users;

    /**
     * @var Organization[]
     */
    protected $organizations;

    public $userCount = 5;

    protected function setUp()
    {
        parent::setUp(); // TODO: Change the autogenerated stub

        $this->user = new User(['password' => '123456']);
        $this->assertTrue($this->user->register([$this->user->createProfile(['nickname' => 'vistart'])]));

        $this->assertNotNull(Yii::$app->authManager->assign((new SetUpOrganization)->name, $this->user));

        $this->assertTrue($this->user->setUpOrganization($this->faker->name));
        $this->organization = $this->user->lastSetUpOrganization;


        $this->users[0] = new User(['password' => '123456']);
        $this->assertTrue($this->users[0]->register([$this->users[0]->createProfile(['nickname' => 'vistart'])]));

        $this->assertNotNull(Yii::$app->authManager->assign((new SetUpOrganization)->name, $this->users[0]));

        $this->assertTrue($this->users[0]->setUpOrganization($this->faker->name));
        $this->organizations[0] = $this->users[0]->lastSetUpOrganization;
        for ($i = 1; $i < $this->userCount = 5; $i++) {
            $this->users[$i] = new User(['password' => '123456']);
            $this->assertTrue($this->users[$i]->register([$this->users[$i]->createProfile(['nickname' => $this->faker->name])]));
            $this->assertTrue($this->organizations[$i - 1]->addAdministrator($this->users[$i]));

            $this->assertTrue($this->users[$i]->setUpDepartment($this->faker->name, $this->organizations[$i - 1]));
            $this->organizations[$i] = $this->users[$i]->lastSetUpOrganization;
        }
    }

    protected function tearDown()
    {
        Organization::deleteAll();
        User::deleteAll();
        parent::tearDown(); // TODO: Change the autogenerated stub
    }

    /**
     * @group organization
     * @group member
     */
    public function testNormal()
    {
        $this->assertFalse($this->organization->isExcludeOtherMembers);
        $this->assertFalse($this->organization->isDisallowMemberJoinInOther);
        $this->assertFalse($this->organization->isOnlyAcceptCurrentOrgMember);
        $this->assertFalse($this->organization->isOnlyAcceptSuperiorOrgMember);

        foreach ($this->organizations as $org)
        {
            $this->assertNotEquals($this->organization->getGUID(), $org->topOrganization->getGUID());
            $this->assertEquals($this->organizations[0]->getGUID(), $org->topOrganization->getGUID());
        }
    }

    /**
     * @group organization
     * @group member
     */
    public function testModify()
    {
        $this->organization->isExcludeOtherMembers = true;
        $this->assertTrue($this->organization->save());
        $this->assertTrue($this->organization->isExcludeOtherMembers);

        $this->organization->isDisallowMemberJoinInOther = true;
        $this->assertTrue($this->organization->save());
        $this->assertTrue($this->organization->isDisallowMemberJoinInOther);

        $this->organization->isOnlyAcceptCurrentOrgMember = true;
        $this->assertTrue($this->organization->save());
        $this->assertTrue($this->organization->isOnlyAcceptCurrentOrgMember);

        $this->organization->isOnlyAcceptSuperiorOrgMember = true;
        $this->assertTrue($this->organization->save());
        $this->assertTrue($this->organization->isOnlyAcceptSuperiorOrgMember);
    }

    /**
     * @group organization
     * @group member
     */
    public function testExcludeOtherMember()
    {
        $this->organizations[0]->isExcludeOtherMembers = true;
        $this->assertTrue($this->organizations[0]->save());
        $orgs = $this->user->getAtOrganizations()->all();
        foreach ($this->organizations as $org) {
            $this->assertTrue($org->topOrganization->isExcludeOtherMembers);
            foreach ($orgs as $o) {
                $this->assertNotEquals($o->topOrganization->getGUID(), $org->topOrganization->getGUID());
            }
            $member = $this->user;
            $this->assertFalse($org->addMember($member));
            try {
                $org->addAdministrator($member);
                $this->fail();
            } catch (IntegrityException $ex) {

            }
        }

        foreach ($this->users as $user) {
            $member = $user;
            $this->assertTrue($this->organization->addMember($member));
        }
    }

    /**
     * @group organization
     * @group member
     */
    public function testDisallowJoinInOther()
    {
        $this->organizations[0]->isDisallowMemberJoinInOther = true;
        $this->assertTrue($this->organizations[0]->save());

        for ($i = 0; $i < $this->userCount; $i++) {
            $this->assertTrue($this->organizations[$i]->topOrganization->isDisallowMemberJoinInOther);

            $member = $this->users[$i];
            $this->assertFalse($this->organization->addMember($member));
            if ($i < $this->userCount - 1) {
                $member = $this->users[$i];
                $this->assertTrue($this->organizations[$i + 1]->addMember($member));
                $this->assertTrue($this->organizations[$i + 1]->removeMember($member));
            }

            try {
                $this->organization->addAdministrator($member);
            } catch (IntegrityException $ex) {

            }
        }

        $orgs = $this->user->getAtOrganizations()->all();
        $this->assertCount(1, $orgs);
        $this->assertEquals($this->organization->getGUID(), $orgs[0]->getGUID());
        $this->assertFalse($orgs[0]->topOrganization->isDisallowMemberJoinInOther);
        foreach ($this->organizations as $org) {
            $member = $this->user;
            $this->assertFalse($org->topOrganization->isExcludeOtherMembers);
            $this->assertNotEquals($this->organization->topOrganization->getGUID(), $org->topOrganization->getGUID());
            $this->assertFalse($org->hasMember($member));
            $this->assertFalse($org->hasMemberInSubordinates($member));
            $this->assertTrue($org->addMember($member));
            $this->assertTrue($org->removeMember($member));
        }
    }
}
