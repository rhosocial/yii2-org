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

use rhosocial\user\User;
use rhosocial\organization\grid\OrganizationListActionColumn;
use rhosocial\organization\widgets\OrganizationListWidget;
use rhosocial\organization\Organization;
use rhosocial\organization\rbac\permissions\SetUpOrganization;
use yii\data\ActiveDataProvider;
use yii\grid\DataColumn;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\web\View;
use yii\widgets\Pjax;

/* @var $user User */
/* @var $this View */
/* @var $dataProvider ActiveDataProvider */
/* @var $orgOnly boolean*/
$this->title = Yii::t('organization', 'Organization List');
$this->params['breadcrumbs'][] = $this->title;
Pjax::begin([
    'id' => 'organization-pjax',
]);
echo OrganizationListWidget::widget([
    'dataProvider' => $dataProvider,
    'orgOnly' => $orgOnly,
    'actionColumn' => OrganizationListWidget::ACTION_COLUMN_DEFAULT,
]);
Pjax::end();
?>
<div class="row">
    <div class="col-md-12">
        <?php if (Yii::$app->authManager->checkAccess($user, (new SetUpOrganization)->name)) :?>
        <?= Html::a(Yii::t('organization', 'Set Up New Organization'), ['set-up-organization'], ['class' => 'btn btn-primary']) ?>
        <?php endif; ?>
    </div>
</div>
