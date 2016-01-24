<?php
class ProfileNdx_setup
{
	public static function install()
	{
		ProfileNdx_indexer_shared::updateDbTableUserIndex();
		ProfileNdx_search_engine::createSearchCacheTable();
		ProfileNdx_search_engine::createSearchLimitsTable();
	}
	
	public static function reinstall()
	{
		ProfileNdx_indexer_shared::rebuildDbNdx();
		ProfileNdx_search_engine::cleanAllFromCache();
		ProfileNdx_search_engine::cleanAllFromLimit();
	}
	
	public static function uninstall()
	{
		ProfileNdx_indexer_shared::clearDbNdx();
		ProfileNdx_search_engine::deleteSearchCacheTable();
		ProfileNdx_search_engine::deleteSearchLimitTable();
	}
}
