<?php
/**
 * Tatoeba Project, free collaborative creation of multilingual corpuses project
 * Copyright (C) 2009  HO Ngoc Phuong Trang <tranglich@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 *
 * @category PHP
 * @package  Tatoeba
 * @author   HO Ngoc Phuong Trang <tranglich@gmail.com>
 * @license  Affero General Public License
 * @link     https://tatoeba.org
 */
use App\Model\CurrentUser;


if (CurrentUser::isMember()) {
    $this->Html->script('/js/reviews/of.ctrl.js', ['block' => 'scriptBottom']);
}

$categories = array(
    'ok' => ['check_circle', __('Sentences marked as "OK"')],
    'unsure' => ['help', __('Sentences marked as "unsure"')],
    'not-ok' => ['error', __('Sentences marked as "not OK"')],
    'all' => ['keyboard_arrow_right', __("All sentences")],
    'outdated' => ['keyboard_arrow_right', __("Outdated reviews")]
);

if (empty($correctnessLabel) || !in_array($correctnessLabel, $categories)) {
    $category = $categories['all'][1];
} else {
    $category = $categories[$correctnessLabel][1];
}

if ($userExists) {
    $title = format(
        __("{user}'s reviews - {category}"),
        array('user' => $username, 'category' => $category)
    );
} else {
    $title = format(__("There's no user called {username}"), array('username' => $username));
}

$this->set('title_for_layout', $this->Pages->formatTitle($title));
?>

<?php if ($userExists) : ?>
<div id="annexe_content" ng-cloak>
    <?php
    if (!CurrentUser::get('settings.users_collections_ratings')) {
        echo '<div class="module">';
        echo $this->Html->tag('p', __('This feature is currently deactivated.'));
        echo $this->Html->tag('p',
            __(
                'You can activate it in your settings: "Activate the feature to review sentences."',
                true
            )
        );
        echo '</div>';
    }
    ?>

    <md-list class="annexe-menu md-whiteframe-1dp" ng-cloak>
        <?php /* @translators: header text in the sidebar of a list of reviews (verb) */ ?>
        <md-subheader><?= __('Filter') ?></md-subheader>
        <?php
        foreach($categories as $categoryKey => $categoryValue) {
            $url = $this->Url->build([
                'action' => 'of',
                $username,
                $categoryKey
            ]);
            ?>
            <md-list-item href="<?= $url ?>">
                <md-icon class="<?= $categoryKey ?>"><?= $categoryValue[0] ?></md-icon>
                <p><?= $categoryValue[1] ?></p>
            </md-list-item>
            <?php
        }
        ?>
    </md-list>
</div>
<?php endif; ?>

<div id="main_content">
    <section class="md-whiteframe-1dp correctness-info">
        <?php
        if (!$userExists) {
            $this->CommonModules->displayNoSuchUser($username);
        } else {
            $title = $this->Paginator->counter(array(
                'format' => $title . ' ' . __("(total {{count}})")
            ));
            ?>
        <md-toolbar class="md-hue-2">
            <div class="md-toolbar-tools">
                <h2><?= $title ?></h2>

                <?php 
                    $options = array(
                        /* @translators: sort option in the list of reviews */
                        array('param' => 'modified', 'direction' => 'desc', 'label' => __x('reviews', 'Most recently updated')),
                        /* @translators: sort option in the list of reviews */
                        array('param' => 'modified', 'direction' => 'asc', 'label' => __x('reviews', 'Least recently updated')),
                        /* @translators: sort option in the list of reviews */
                        array('param' => 'created', 'direction' => 'desc', 'label' => __x('reviews', 'Newest first')),
                        /* @translators: sort option in the list of reviews */
                        array('param' => 'created', 'direction' => 'asc', 'label' => __x('reviews', 'Oldest first')),
                        /* @translators: sort option in the list of reviews */
                        array('param' => 'sentence_id', 'direction' => 'desc', 'label' => __('Newest sentences') ),
                        /* @translators: sort option in the list of reviews */
                        array('param' => 'sentence_id', 'direction' => 'asc', 'label' => __('Oldest sentences') )
                    );
                    echo $this->element('sort_menu', array('options' => $options));
                ?>

            </div>
        </md-toolbar>

        <?php
            $this->Pagination->display();

            $type = 'mainSentence';
            $parentId = null;
            $withAudio = false;
            foreach ($corpus as $item) {
                $sentence = $item->sentence;
                $sentenceData = $this->Sentences->sentenceForAngular($sentence);
                $sentenceData = str_replace('{{', '\{\{', $sentenceData);
                $correctness = $item->correctness;
        ?>
                <div ng-controller="ReviewsController as ctrl"
                     ng-init="ctrl.initSentenceAndCorrectness(<?= $sentenceData ?>, <?= $correctness ?>)"
                     ng-cloak
                     class="layout-row flex">
         <?php
                if (empty($sentence->id)) {
                    $sentenceId = $item->sentence_id;
                    $linkToSentence = $this->Html->link(
                        '#'.$sentenceId,
                        array(
                            'controller' => 'sentences',
                            'action' => 'show',
                            $sentenceId
                        )
                    );

                    echo $this->Html->div('sentence deleted',
                        format(
                            __('Sentence {id} has been deleted.'),
                            array('id' => $linkToSentence)
                        )
                    );
                } else {
                    $this->Sentences->displayGenericSentence(
                        $sentence,
                        $type,
                        $parentId,
                        $withAudio
                    );
                }

            ?>

            <?php if($userIsReviewer): ?>
                <div class="correctness-icons">
                    <icon-with-progress is-loading="ctrl.iconsInProgress.reviewOk">
                        <md-button class="md-icon-button" ng-click="ctrl.setReview(1)" ng-if="ctrl.correctness !== 1">
                            <md-icon>check_circle</md-icon>
                            <md-tooltip><?= __('Mark as "OK"') ?></md-tooltip>
                        </md-button>
                        <md-button class="md-icon-button" ng-click="ctrl.resetReview()" ng-if="ctrl.correctness === 1">
                            <md-icon class="ok">check_circle</md-icon>
                            <md-tooltip><?= __('Unmark sentence') ?></md-tooltip>
                        </md-button>
                    </icon-with-progress>

                    <icon-with-progress is-loading="ctrl.iconsInProgress.reviewUnsure">
                        <md-button class="md-icon-button" ng-click="ctrl.setReview(0)" ng-if="ctrl.correctness !== 0">
                            <md-icon>help</md-icon>
                            <md-tooltip><?= __('Mark as "unsure"') ?></md-tooltip>
                        </md-button>
                        <md-button class="md-icon-button" ng-click="ctrl.resetReview()" ng-if="ctrl.correctness === 0">
                            <md-icon class="unsure">help</md-icon>
                            <md-tooltip><?= __('Unmark sentence') ?></md-tooltip>
                        </md-button>
                    </icon-with-progress>

                    <icon-with-progress is-loading="ctrl.iconsInProgress.reviewNotOk">
                        <md-button class="md-icon-button" ng-click="ctrl.setReview(-1)" ng-if="ctrl.correctness !== -1">
                            <md-icon>error</md-icon>
                            <md-tooltip><?= __('Mark as "not OK"') ?></md-tooltip>
                        </md-button>
                        <md-button class="md-icon-button not-ok" ng-click="ctrl.resetReview()" ng-if="ctrl.correctness === -1">
                            <md-icon class="not-ok">error</md-icon>
                            <md-tooltip><?= __('Unmark sentence') ?></md-tooltip>
                        </md-button>
                    </icon-with-progress>
                </div>

            <?php else:
                $categoryLabel = $correctness == 1 ? 'ok' : ($correctness == 0 ? 'unsure' : 'not-ok');
                echo $this->Html->div(
                    'correctness-icons',
                    '<md-icon class="' . $categoryLabel . '">' . $categories[$categoryLabel][0] . '</md-icon>',
                    array('title' => $this->Date->nice($item->modified))
                );
            ?>
            <?php endif;?>
            <?php
                echo '</div>';
            }

            $this->Pagination->display();
        }
        ?>
    </div>
</div>
