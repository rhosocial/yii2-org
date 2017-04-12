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

use rhosocial\organization\Member;
use rhosocial\organization\grid\MemberListActionColumn;
use yii\data\ActiveDataProvider;
use yii\grid\DataColumn;
use yii\grid\GridView;
use yii\grid\SerialColumn;
use yii\web\View;

/* @var $dataProvider ActiveDataProvider */
/* @var $this View */
/* @var $tips boolean|array */
echo GridView::widget([
    'dataProvider' => $dataProvider,
    'caption' => 'Here are all members of the organization / department:',
    'columns' => [
        ['class' => SerialColumn::class],
        'user_id' => [
            'class' => DataColumn::class,
            'label' => Yii::t('user', 'User ID'),
            'content' => function ($model, $key, $index, $column) {
                return $model->memberUser->getID();
            },
            'contentOptions' => function ($model, $key, $index, $column) {
                /* @var $model Member */
                if ($model->memberUser->getID() != Yii::$app->user->identity->getID()) {
                    return [];
                }
                return ['bgcolor' => '#00FF00'];
            },
        ],
        'name' => [
            'class' => DataColumn::class,
            'label' => Yii::t('user', 'Name'),
            'content' => function ($model, $key, $index, $column) {
                if (!$model->memberUser || !$model->memberUser->profile) {
                    return null;
                }
                return $model->memberUser->profile->first_name . ' ' . $model->memberUser->profile->last_name;
            }
        ],
        'position',
        'role' => [
            'class' => DataColumn::class,
            'label' => Yii::t('organization', 'Role'),
            'content' => function ($model, $key, $index, $column) {
                if (empty($model->role)) {
                    return null;
                }
                $role = Yii::$app->authManager->getRole($model->role);
                if (empty($role)) {
                    return null;
                }
                return Yii::t('organization', $role->description);
            },
        ],
        'createdAt' => [
            'class' => DataColumn::class,
            'attribute' => 'createdAt',
            'label' => Yii::t('user', 'Creation Time'),
            'content' => function ($model, $key, $index, $column) {
                /* @var $model Member */
                return $column->grid->formatter->format($model->getCreatedAt(), 'datetime');
            },
        ],
        'updatedAt' => [
            'class' => DataColumn::class,
            'attribute' => 'updatedAt',
            'label' => Yii::t('user', 'Last Updated Time'),
            'content' => function ($model, $key, $index, $column) {
                /* @var $model Member */
                return $column->grid->formatter->format($model->getUpdatedAt(), 'datetime');
            },
        ],
        'action' => [
            'class' => MemberListActionColumn::class,
        ],
    ],
    'tableOptions' => [
        'class' => 'table table-striped'
    ],
]);
echo $this->render('member-list-tips', ['tips' => $tips]);
