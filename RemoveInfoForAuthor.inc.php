<?php

/**
 * @file plugins/generic/RemoveInfoForAuthorPlugin/RemoveInfoForAuthorPlugin.inc.php
 *
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RemoveInfoForAuthorPlugin
 * @ingroup plugins_generic_RemoveInfoForAuthorPlugin
 *  
 * @brief Hide the round status from the author, 
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class RemoveInfoForAuthorPlugin extends GenericPlugin {

	/**
	 * @copydoc LazyLoadPlugin::register()
	 */
	
	function register($category, $path) {
		if (parent::register($category, $path)) {
			if ($this->getEnabled()) {
				// Hook status in Submissions List in dashboard 
				HookRegistry::register('Submission::getProperties::values', array(&$this, 'hookGetProperties'));
								
				//Notification for Author dashboard
				HookRegistry::register('NotificationManager::getNotificationMessage', array(&$this, 'hookNotificationMessage'));
			}
			return true;
		}
		return false;
	}
	
	/**
	* @copydoc Plugin::isSitePlugin()
	*/
	function isSitePlugin() {
		// This is a site-wide plugin.
		return false;
	}

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.generic.RemoveInfoForAuthor.name');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.generic.RemoveInfoForAuthor.description');
	}

	function hookGetProperties($hookname, $args) {
		// HookRegistry::call('Submission::getProperties::values', array(&$values, $submission, $props, $args));
		
		$request = $args[3]['request'];
		$values = & $args[0];
		$submission = $args[1];
		$currentUser = $request->getUser();
		$saDao = DAORegistry::getDAO('StageAssignmentDAO');
		$stageAssignments = $saDao->getBySubmissionAndRoleId( $submission->getId(), ROLE_ID_AUTHOR, null, $currentUser->getId() ) ;
		if ( $currentUser->hasRole(ROLE_ID_AUTHOR, $request->getContext()->getId())  && $stageAssignments->getCount() > 0 ) {
			//$reviewRounds = $values['reviewRounds'];
			if (sizeof($values['reviewRounds']) > 0 ) {
				for($i=0; $i < sizeof($values['reviewRounds']);$i++) {
					if ( in_array($values['reviewRounds'][$i]['statusId'], 
							array(REVIEW_ROUND_STATUS_PENDING_REVIEWERS, 
								REVIEW_ROUND_STATUS_PENDING_REVIEWS, 
								REVIEW_ROUND_STATUS_REVIEWS_READY,
								REVIEW_ROUND_STATUS_REVIEWS_COMPLETED,
								REVIEW_ROUND_STATUS_REVIEWS_OVERDUE))) { 
						$values['reviewRounds'][$i]['status'] = __('submission.status.review');
					}
					
				}
			}
		}
		
	}
	
	function hookNotificationMessage($hookname, $args) {
	//HookRegistry::call('NotificationManager::getNotificationMessage', array(&$notification, &$message));		
		$notification = & $args[0];
		$message = & $args[1];

		if ($notification->getType() == NOTIFICATION_TYPE_REVIEW_ROUND_STATUS) {

		// from PKPNotificationManager :145
			assert($notification->getAssocType() == ASSOC_TYPE_REVIEW_ROUND && is_numeric($notification->getAssocId()));
			$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
			$reviewRound = $reviewRoundDao->getById($notification->getAssocId());
			$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');

			$request = Application::getRequest();
			AppLocale::requireComponents(LOCALE_COMPONENT_APP_EDITOR); // load review round status keys.
			$user = $request->getUser();
			$stageAssignments = $stageAssignmentDao->getBySubmissionAndRoleId($reviewRound->getSubmissionId(), ROLE_ID_AUTHOR, null, $user->getId());
			$isAuthor = $stageAssignments->getCount() > 0;
			$stageAssignments->close();
			$message = __($this->_getRoundStatus($reviewRound, $isAuthor));
			}
		return true;
	}

		/**
	 * Get locale key associated with current status
	 * @param reviewRound object
	 * @param $isAuthor boolean True if the status is to be shown to the author (slightly tweaked phrasing)
	 * @return int
	 * 
	 * override ReviewRound to hide some status to Author
	 * 
	 */
	function _getRoundStatus($reviewRound, $isAuthor = false) {
		switch ($reviewRound->determineStatus()) {
			case REVIEW_ROUND_STATUS_PENDING_REVIEWERS:
				return $isAuthor ? 'submission.status.review' : 'editor.submission.roundStatus.pendingReviewers';
			case REVIEW_ROUND_STATUS_PENDING_REVIEWS:
				return $isAuthor ? 'submission.status.review' : 'editor.submission.roundStatus.pendingReviews';
			case REVIEW_ROUND_STATUS_REVIEWS_READY:
				return $isAuthor ? 'submission.status.review' : 'editor.submission.roundStatus.reviewsReady';
			case REVIEW_ROUND_STATUS_REVIEWS_COMPLETED:
				return $isAuthor ? 'submission.status.review' : 'editor.submission.roundStatus.reviewsCompleted';
			case REVIEW_ROUND_STATUS_REVIEWS_OVERDUE:
				return $isAuthor ? 'submission.status.review' : 'editor.submission.roundStatus.reviewOverdue';
			default: $reviewRound->getStatusKey($isAuthor);
		}
	}

}
?>
