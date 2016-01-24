<?php

class ProfileNdx_search_view extends XFCP_ProfileNdx_search_view
{
	public function actionProfilesearch()
	{
		$visitor = XenForo_Visitor::getInstance();
		if(!$visitor['user_id']){
			throw $this->getNoPermissionResponseException();
		}
		unset($visitor);
		return $this->responseView('XenForo_ViewPublic_Base',
								   'kiror_search_fill',
								   array('searchType'=>'profile_content')
		);
	}
	
	public function actionProfileresults()
	{
		$visitor = XenForo_Visitor::getInstance();
		if(!$visitor['user_id']){
			throw $this->getNoPermissionResponseException();
		}
		unset($visitor);
		$query=mb_strtolower($this->_input->filterSingle('keywords', XenForo_Input::STRING),'UTF-8');
		if (trim($query)=='')
			return $this->responseError(new XenForo_Phrase('requested_search_not_found'), 404);
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink(
				'search/profileresultsdisplay/', '', array('q'=>$query)),
			'');
	}
	
	public function actionProfileresultsdisplay()
	{
		$maxRes=100;
		$visitor = XenForo_Visitor::getInstance();
		if(!$visitor['user_id']){
			throw $this->getNoPermissionResponseException();
		}
		$uid=$visitor['user_id'];
		unset($visitor);
		$query=mb_strtolower($this->_input->filterSingle('q', XenForo_Input::STRING),'UTF-8');
		$q=substr($query, 0, 255);
		ProfileNdx_search_engine::cleanOldsFromLimit();
		ProfileNdx_search_engine::cleanOldsFromCache();
		$permission=ProfileNdx_search_engine::userCanSearch($uid,$q);
		if(!$permission['status']){
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink(
					'search/profileresultswait/',
					'',
					array('redirect'=>
						XenForo_Link::buildPublicLink(
							'search/profileresultsdisplay/',
							'',
							array('q'=>$query)
						  ),
						  'wait'=>$permission['wait']
						)
					),
				'');
		}
		$viewpar=ProfileNdx_search_engine::getFromCache($q);
		if($viewpar==null){
			$results=(new ProfileNdx_search_engine())->search($q);
			$viewpar=array('search'=>array('search_query'=>$query),
						   'results'=>array_slice($results,0,$maxRes),
						   'totalResults'=> count($results),
						   'resultStartOffset'=>0,
						   'resultEndOffset'=>min(count($results),$maxRes)
						  );
			$results=(new ProfileNdx_search_engine())->toHtml($viewpar['results']);
			$viewpar['results']=$results;
			ProfileNdx_search_engine::saveToCache($q,$viewpar);
		}
		return $this->responseView('XenForo_ViewPublic_Base',
								   'kiror_search_results',
								   $viewpar);
	}
	
	public function actionProfileresultswait()
	{
		$viewpar=array(
		'redirect'=>$this->_input->filterSingle('redirect', XenForo_Input::STRING),
		'cooldown'=>$this->_input->filterSingle('wait', XenForo_Input::INT)
		);
		return $this->responseView('XenForo_ViewPublic_Base',
								   'kiror_waitscreen',
								   $viewpar);
	}
}
