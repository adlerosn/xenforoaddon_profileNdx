<?php
class ProfileNdx_indexer_controller
{
	public static function load_class($class, array &$extend)
	{
		if ($class == 'XenForo_ControllerPublic_Account')
		{
			$extend[] = 'ProfileNdx_indexer_extender';
		}
	}
}
