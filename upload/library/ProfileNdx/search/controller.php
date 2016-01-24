<?php

class ProfileNdx_search_controller
{
	public static function callback($class, array &$extend)
	{
		if ($class == 'XenForo_ControllerPublic_Search')
			$extend[]='ProfileNdx_search_view';
	}
}
