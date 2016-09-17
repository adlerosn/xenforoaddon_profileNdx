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
		$returnValue = parent::actionPersonalDetailsSave();
		ProfileNdx_indexer_shared::updateDbTableUserIndex();
		return $returnValue;
	}

}
