<?php

class ProfileNdx_indexer_extender extends XFCP_ProfileNdx_indexer_extender
{
	/**
	 * Lines 231 to 241 from \\(xf)/library/XenForo/ControllerPublic/Account.php
	 * @override
	 * 
	 * Save profile data
	 *
	 * @return XenForo_ControllerResponse_Redirect
	 */
	public function actionPersonalDetailsSave()
	{
		$this->_assertPostOnly();

		if (!XenForo_Visitor::getInstance()->canEditProfile())
		{
			return $this->responseNoPermission();
		}

		$settings = $this->_input->filter(array(
			'gender'     => XenForo_Input::STRING,
			'custom_title' => XenForo_Input::STRING,
			// user_profile
			'status'     => XenForo_Input::STRING,
			'homepage'   => XenForo_Input::STRING,
			'location'   => XenForo_Input::STRING,
			'occupation' => XenForo_Input::STRING,
			'dob_day'    => XenForo_Input::UINT,
			'dob_month'  => XenForo_Input::UINT,
			'dob_year'   => XenForo_Input::UINT,
			// user_option
			'show_dob_year' => XenForo_Input::UINT,
			'show_dob_date' => XenForo_Input::UINT,
		));
		$settings['about'] = $this->getHelper('Editor')->getMessageText('about', $this->_input);
		$settings['about'] = XenForo_Helper_String::autoLinkBbCode($settings['about']);

		$visitor = XenForo_Visitor::getInstance();
		if ($visitor['dob_day'] && $visitor['dob_month'] && $visitor['dob_year'])
		{
			// can't change dob if set
			unset($settings['dob_day'], $settings['dob_month'], $settings['dob_year']);
		}

		if (!$visitor->hasPermission('general', 'editCustomTitle'))
		{
			unset($settings['custom_title']);
		}

		$status = $settings['status'];
		unset($settings['status']); // see below for status update

		if ($status !== '')
		{
			$this->assertNotFlooding('post');
		}

		$customFields = $this->_input->filterSingle('custom_fields', XenForo_Input::ARRAY_SIMPLE);
		$customFieldsShown = $this->_input->filterSingle('custom_fields_shown', XenForo_Input::STRING, array('array' => true));

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
		$writer->setExistingData(XenForo_Visitor::getUserId());
		$writer->bulkSet($settings);
		$writer->setCustomFields($customFields, $customFieldsShown);

		$spamModel = $this->_getSpamPreventionModel();

		if ($settings['about'] && !$writer->hasErrors() && $spamModel->visitorRequiresSpamCheck())
		{
			$spamResult = $spamModel->checkMessageSpam($settings['about'], array(), $this->_request);
			switch ($spamResult)
			{
				case XenForo_Model_SpamPrevention::RESULT_MODERATED:
				case XenForo_Model_SpamPrevention::RESULT_DENIED;
					$spamModel->logSpamTrigger('user_about', XenForo_Visitor::getUserId());
					$writer->error(new XenForo_Phrase('your_content_cannot_be_submitted_try_later'));
					break;
			}
		}

		$writer->preSave();

		if ($dwErrors = $writer->getErrors())
		{
			return $this->responseError($dwErrors);
		}

		$writer->save();
		
		//EXTENSION POINT. It's the extension point that is present in the UML you don't have.
		//UPDATE INDEX BEGIN
		ProfileNdx_indexer_shared::updateDbTableUserIndex();
		//UPDATE INDEX END

		$redirectParams = array();

		if ($status !== '' && $visitor->canUpdateStatus())
		{
			$this->getModelFromCache('XenForo_Model_UserProfile')->updateStatus($status);
			$redirectParams['status'] = $status;
		}

		if ($this->_noRedirect())
		{
			$user = $writer->getMergedData();

			// send new avatar URLs if the user's gender has changed
			if (!$user['avatar_date'] && !$user['gravatar'] && $writer->isChanged('gender'))
			{
				return $this->responseView('XenForo_ViewPublic_Account_GenderChange', '', array('user' => $user));
			}

		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('account/personal-details'),
			null,
			$redirectParams
		);
	}

}
