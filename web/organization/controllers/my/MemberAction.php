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

namespace rhosocial\organization\web\organization\controllers\my;

use rhosocial\organization\exceptions\NotMemberOfOrganizationException;
use rhosocial\organization\exceptions\OrganizationNotFoundException;
use rhosocial\organization\web\organization\Module;
use rhosocial\organization\Organization;
use rhosocial\user\User;
use Yii;
use yii\base\Action;
use yii\data\ActiveDataProvider;

/**
 * @version 1.0
 * @author vistart <i@vistart.me>
 */
class MemberAction extends Action
{
    /**
     * Check access.
     * Check whether the organization is valid or not.
     * The organization is null or new record, it will be considered as invalid. If so, the OrganizationNotFoundException
     * will be thrown.
     *
     * Check whether the organization has the member ($user).
     * If not, the NotMemberOfOrganizationException will be thrown.
     *
     * @param Organization $org
     * @param User $user
     * @return boolean
     * @throws OrganizationNotFoundException
     * @throws NotMemberOfOrganizationException
     */
    public static function checkAccess($org, $user)
    {
        if (!$org || $org->getIsNewRecord()) {
            throw new OrganizationNotFoundException();
        }
        if (!$org->hasMember($user)) {
            throw new NotMemberOfOrganizationException();
        }
        return true;
    }

    /**
     * Run action.
     * List all members of the organization or department.
     * @param Organization|string|integer $org
     * @return string rendering results.
     */
    public function run($org)
    {
        $organization = Module::getOrganization($org);
        $user = Yii::$app->user->identity;
        static::checkAccess($organization, $user);
        $searchModel = $organization->getNoInitMember()->getSearchModel();
        $searchModel->query = $searchModel->query->organization($organization);
        $dataProvider = new ActiveDataProvider([
            'query' => $organization->getMembers(),
            'pagination' => [
                'pageParam' => 'member-param',
                'defaultPageSize' => 20,
                'pageSizeParam' => 'member-per-param',
            ],
            'sort' => [
                'sortParam' => 'member-sort',
                'attributes' => [
                    'user_id',
                ],
            ],
        ]);
        $dataProvider = $searchModel->search(Yii::$app->request->get());
        return $this->controller->render('member', [
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel,
            'organization' => $organization,
            'user' => Yii::$app->user->identity
        ]);
    }
}
