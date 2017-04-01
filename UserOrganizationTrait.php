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

namespace rhosocial\organization;

use rhosocial\organization\queries\MemberQuery;
use rhosocial\organization\queries\OrganizationQuery;
use rhosocial\organization\rbac\permissions\SetUpOrganization;
use rhosocial\organization\rbac\permissions\SetUpDepartment;
use rhosocial\organization\rbac\permissions\RevokeOrganization;
use rhosocial\organization\rbac\permissions\RevokeDepartment;
use rhosocial\organization\rbac\roles\DepartmentAdmin;
use rhosocial\organization\rbac\roles\DepartmentCreator;
use rhosocial\organization\rbac\roles\OrganizationAdmin;
use rhosocial\organization\rbac\roles\OrganizationCreator;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;

/**
 * @property string $guidAttribute GUID Attribute.
 * @property-read Member[] $ofMembers
 * @property-read Organization[] $atOrganizations
 * @property-read Organization[] $creatorsAtOrganizations
 * @property-read Organization[] $administratorsAtOrganizations
 *
 * @version 1.0
 * @author vistart <i@vistart.me>
 */
trait UserOrganizationTrait
{
    public $organizationClass = Organization::class;
    public $memberClass = Member::class;
    private $noInitOrganization;
    private $noInitMember;
    public $lastSetUpOrganization;
    /**
     * @return Organization
     */
    protected function getNoInitOrganization()
    {
        if (!$this->noInitOrganization) {
            $class = $this->organizationClass;
            $this->noInitOrganization = $class::buildNoInitModel();
        }
        return $this->noInitOrganization;
    }
    /**
     * @return Member
     */
    protected function getNoInitMember()
    {
        if (!$this->noInitMember) {
            $class = $this->memberClass;
            $this->noInitMember = $class::buildNoInitModel();
        }
        return $this->noInitMember;
    }

    /**
     * 
     * @return MemberQuery
     */
    public function getOfMembers()
    {
        return $this->hasMany($this->memberClass, [$this->getNoInitMember()->memberAttribute => $this->guidAttribute])->inverseOf('memberUser');
    }

    /**
     * 
     * @return MemberQuery
     */
    public function getOfCreators()
    {
        return $this->getOfMembers()->andWhere(['role' => [(new DepartmentCreator)->name, (new OrganizationCreator)->name]]);
    }

    /**
     * 
     * @return MemberQuery
     */
    public function getOfAdministrators()
    {
        return $this->getOfMembers()->andWhere(['role' => [(new DepartmentAdmin)->name, (new OrganizationAdmin)->name]]);
    }

    /**
     * 
     * @return OrganizationQuery
     */
    public function getAtOrganizations()
    {
        return $this->hasMany($this->organizationClass, [$this->guidAttribute => $this->getNoInitMember()->createdByAttribute])->via('ofMembers');
    }

    /**
     * 
     * @return OrganizationQuery
     */
    public function getCreatorsAtOrganizations()
    {
        return $this->hasMany($this->organizationClass, [$this->guidAttribute => $this->getNoInitMember()->createdByAttribute])->via('ofCreators');
    }

    /**
     * 
     * @return OrganizationQuery
     */
    public function getAdministratorsAtOrganizations()
    {
        return $this->hasMany($this->organizationClass, [$this->guidAttribute => $this->getNoInitMember()->createdByAttribute])->via('ofAdministrators');
    }

    /**
     * Set up organization.
     * @param string $name
     * @param string $nickname
     * @param integer $gravatar_type
     * @param string $gravatar
     * @param string $timezone
     * @param string $description
     * @return boolean Whether indicate the setting-up succeeded or not.
     */
    public function setUpOrganization($name, $nickname = '', $gravatar_type = 0, $gravatar = '', $timezone = 'UTC', $description = '')
    {
        $accessChecker = Yii::$app->authManager;
        if (!$accessChecker->checkAccess($this, (new SetUpOrganization)->name)) {
            throw new InvalidParamException("You do not have permission to set up organization.");
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $models = $this->createOrganization($name, null, $nickname = '', $gravatar_type = 0, $gravatar = '', $timezone = 'UTC', $description = '');
            $this->setUpBaseOrganization($models);
            $transaction->commit();
        } catch (\Exception $ex) {
            $transaction->rollBack();
            Yii::error($ex->getMessage(), __METHOD__);
            throw $ex;
        }
        $this->lastSetUpOrganization = is_array($models) ? $models[0] : $models;
        return true;
    }

    /**
     * Set up organization.
     * @param string $name
     * @param Organization $parent
     * @param string $nickname
     * @param integer $gravatar_type
     * @param string $gravatar
     * @param string $timezone
     * @param string $description
     * @return boolean Whether indicate the setting-up succeeded or not.
     */
    public function setUpDepartment($name, $parent = null, $nickname = '', $gravatar_type = 0, $gravatar = '', $timezone = 'UTC', $description = '')
    {
        if (!($parent instanceof $this->organizationClass)) {
            throw new InvalidParamException('Invalid Parent Parameter.');
        }
        $accessChecker = Yii::$app->authManager;
        if (!$accessChecker->checkAccess($this, (new SetUpDepartment)->name, ['organization' => $parent])) {
            throw new InvalidParamException("You do not have permission to set up department.");
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $models = $this->createDepartment($name, $parent, $nickname = '', $gravatar_type = 0, $gravatar = '', $timezone = 'UTC', $description = '');
            $this->setUpBaseOrganization($models);
            $transaction->commit();
        } catch (\Exception $ex) {
            $transaction->rollBack();
            Yii::error($ex->getMessage(), __METHOD__);
            throw $ex;
        }
        $this->lastSetUpOrganization = is_array($models) ? $models[0] : $models;
        return true;
    }

    /**
     * Set up base organization.
     * @param Organization $models
     * @return boolean
     * @throws InvalidConfigException
     * @throws \Exception
     */
    protected function setUpBaseOrganization($models)
    {
        $model = null;
        $associatedModels = [];
        if (is_array($models)) {
            if (!array_key_exists(0, $models)) {
                throw new InvalidConfigException('Invalid Organization Model.');
            }
            $model = $models[0];
            $associatedModels = array_key_exists('associatedModels', $models) ? $models['associatedModels'] : [];
        } elseif ($models instanceof $this->organizationClass) {
            $model = $models;
        }
        $result = $model->register($associatedModels);
        if ($result instanceof \Exception) {
            throw $result;
        }
        if ($result !== true) {
            throw new \Exception('Failed to set up.');
        }
        return true;
    }

    /**
     * Create organization.
     * @param string $name
     * @param Organization $parent
     * @param string $nickname
     * @param string $gravatar_type
     * @param string $gravatar
     * @param string $timezone
     * @param string $description
     * @return Organization
     */
    public function createOrganization($name, $parent = null, $nickname = '', $gravatar_type = 0, $gravatar = '', $timezone = 'UTC', $description = '')
    {
        return $this->createBaseOrganization($name, $parent, $nickname, $gravatar_type, $gravatar, $timezone, $description);
    }

    /**
     * Create department.
     * @param string $name
     * @param Organization $parent
     * @param string $nickname
     * @param string $gravatar_type
     * @param string $gravatar
     * @param string $timezone
     * @param string $description
     * @return Organization
     */
    public function createDepartment($name, $parent = null, $nickname = '', $gravatar_type = 0, $gravatar = '', $timezone = 'UTC', $description = '')
    {
        return $this->createBaseOrganization($name, $parent, $nickname, $gravatar_type, $gravatar, $timezone, $description, Organization::TYPE_DEPARTMENT);
    }

    /**
     * Create Base Organization.
     * @param string $name
     * @param Organization $parent
     * @param string $nickname
     * @param integer $gravatar_type
     * @param string $gravatar
     * @param string $timezone
     * @param string $description
     * @param integer $type
     * @return Organization
     * @throws InvalidParamException throw if setting parent failed. Possible reasons include:
     * - The parent is itself.
     * - The parent has already been its ancestor.
     * - The current organization has reached the limit of ancestors.
     */
    protected function createBaseOrganization($name, $parent = null, $nickname = '', $gravatar_type = 0, $gravatar = '', $timezone = 'UTC', $description = '', $type = Organization::TYPE_ORGANIZATION)
    {
        $class = $this->organizationClass;
        $profileConfig = [
            'name' => $name,
            'nickname' => $nickname,
            'gravatar_type' => $gravatar_type,
            'gravatar' => $gravatar,
            'timezone' => $timezone,
            'description' => $description,
        ];
        $organization = new $class(['type' => $type, 'creatorModel' => $this, 'profileConfig' => $profileConfig]);
        if (empty($parent)) {
            $organization->setNullParent();
        } elseif ($organization->setParent($parent) === false) {
            throw new InvalidParamException("Failed to set parent.");
        }
        return $organization;
    }

    /**
     * Revoke organization or department.
     * @param static|string|integer $organization
     * @param boolean $revokeIfHasChildren
     * @throws InvalidParamException throw if current user is not the creator of organization.
     */
    public function revokeOrganization($organization, $revokeIfHasChildren = false)
    {
        if (!($organization instanceof $this->organizationClass))
        {
            $class = $this->organizationClass;
            if (is_numeric($organization)) {
                $organization = $class::find()->id($organization)->one();
            } elseif (is_string($organization)) {
                $organization = $class::find()->guid($organization)->one();
            }
        }
        if (!($organization instanceof $this->organizationClass)) {
            throw new InvalidParamException('Invalid Organization.');
        }
        $accessChecker = Yii::$app->authManager;
        if ($organization->type == Organization::TYPE_ORGANIZATION) {
            if (!$accessChecker->checkAccess($this, (new RevokeOrganization)->name, [
                'organization' => $organization,
            ])) {
                throw new InvalidParamException("You do not have permission to revoke it.");
            }
        } elseif ($organization->type == Organization::TYPE_DEPARTMENT) {
            if (!$accessChecker->checkAccess($this, (new RevokeDepartment)->name, [
                'organization' => $organization,
            ])) {
                throw new InvalidParamException("You do not have permission to revoke it.");
            }
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $result = $organization->deregister();
            if ($result instanceof \Exception){
                throw $result;
            }
            if ($result !== true) {
                throw new InvalidParamException("Failed to revoke.");
            }
            $transaction->commit();
        } catch (\Exception $ex) {
            $transaction->rollBack();
            Yii::error($ex->getMessage(), __METHOD__);
            throw $ex;
        }
        return true;
    }

    /**
     * 
     * @param Organization $organization
     */
    public function isOrganizationCreator($organization)
    {
        $member = $organization->getMember($this);
        if (!$member) {
            return false;
        }
        return $member->isCreator();
    }

    /**
     * 
     * @param Organization $organization
     */
    public function isOrganizationAdministrator($organization)
    {
        $member = $organization->getMember($this);
        if (!$member) {
            return false;
        }
        return $member->isAdministrator();
    }
}
