<?php
/*
 * This file is part of Extension:BreadCrumbs2
 *
 * Copyright (C) 2007; Eric Hartwell and Ike Hecht.
 *
 * Distributed under the terms of the CC-BY-3.0 license.
 * Terms and conditions of the license can be found at
 * <https://creativecommons.org/licenses/by/3.0/>
 *
 * @author Eric Hartwell (http://www.ehartwell.com/InfoDabble/BreadCrumbs2)
 * @author Ike Hecht
 * @license CC-BY-3.0
 */
use MediaWiki\MediaWikiServices;

class BreadCrumbs2Hooks {

	public static function onSkinSubPageSubtitle( string &$subpages, Skin $skin, OutputPage $out ) {
		# Only show breadcrumbs when viewing the page, not editing, etc.
		# The following line should perhaps utilize Action::getActionName( $skin->getContext() );
		if ( $skin->getRequest()->getVal( 'action', 'view' ) !== 'view' ) {
			return true;
		}

		# Get the list of categories for the current page
		$categories = $skin->getOutput()->getCategories();

		if (($key = array_search('MediaWiki', $categories)) !== false) {
    			unset($categories[$key]);
			$categories=array_values($categories);
		}

		$title = $skin->getRelevantTitle();

		# Search a breadcrubs for parent current of the current page
		$breadCrumbs2 = new BreadCrumbs2( $categories, $title, $skin->getUser() );

		# If there is no breadcrumbs for parent category of the current page, it creates it
		if(!$breadCrumbs2->hasBreadCrumbs()){
			$updateClass = new BreadCrumbs2Update;
			$crumbs_inj = $updateClass->addBreadCrumb($categories);
			if(!empty($crumbs_inj)){
				$breadCrumbs2 = new BreadCrumbs2( $categories, $title, $skin->getUser(), ($crumbs_inj ?? '') );
			}
		}

		# Set the breadcrumbs
		$sidebarText = $breadCrumbs2->getSidebarText();
		$skin->getOutput()->setProperty( 'BreadCrumbs2', $breadCrumbs2 );

		$config = MediaWikiServices::getInstance()->getMainConfig();
		$hideUnmatched = $config->get( 'BreadCrumbs2HideUnmatched' );
		if ( $hideUnmatched && !$breadCrumbs2->hasBreadCrumbs() ) {
			// If no breadcrumbs are defined for this page, do nothing.
			return true;
		}

		# See if we should change the site logo
		# Don't go overboard with this... subtle is better.
		$logoPath = $breadCrumbs2->getLogoPath();
		if ( $logoPath ) {
			global $wgLogo, $wgScriptPath;
			// FIXME: Does not work with modern MediaWiki versions and modern skins, which have already
			// set the logo at this point using the ResourceLoader
			$wgLogo = $wgScriptPath . '/' . $logoPath;
		}

		$subpages = $breadCrumbs2->getOutput() . $subpages;
		$removeBasePageLink = $config->get( 'BreadCrumbs2RemoveBasePageLink' );
		if ( $removeBasePageLink && $title->isSubpage() && $breadCrumbs2->hasBreadCrumbs() ) {
			return false;
		}

		return true;
	}

	/**
	 * @param Skin $skin
	 * @param array &$sidebar
	 */
	public static function onSidebarBeforeOutput( Skin $skin, array &$sidebar ) {
		/** @var BreadCrumbs2 $breadCrumbs2 */
		$breadCrumbs2 = $skin->getOutput()->getProperty( 'BreadCrumbs2' );
		if ( !$breadCrumbs2 ) {
			return;
		}
		$sidebarText = $breadCrumbs2->getSidebarText();
		if ( $sidebarText ) {
			# See if there's a corresponding link in the sidebar and mark it as active.
			# This is especially useful for skins that display the sidebar as a tab bar.
			foreach ( $sidebar as $bar => $cont ) {
				foreach ( $cont as $key => $val ) {
					if ( isset( $val['text'] ) && $val['text'] === $sidebarText ) {
						$sidebar[$bar][$key]['active'] = true;
						break;
					}
				}
			}
		}
	}
	
	/**
	 * Source: https://www.mediawiki.org/wiki/Manual:Hooks/PageSaveComplete
	 * @param Skin $skin
	 * @param array &$sidebar
	 * @param $wikiPage: WikiPage that was modified
	 * @param $user: user performing the modification
	 * @param $summary: edit summary/comment
	 * @param $flags: EDIT_… flags passed to WikiPage::doEditContent()
	 * @param $revisionRecord: new MediaWiki\Revision\RevisionRecord of the article
	 * @param $editResult: object storing information about the effects of this edit.
	 * @retrun return false to stop other hook handlers from being called; save cannot be aborted.
	 */
	public static function onPageSaveComplete(  $wikiPage, $user, $summary, $flags, $revisionRecord, $editResult ) {
												
		wfDebug("BreadCrumbs2 Hook onPageSaveComplete");
	}
	
        /**
         * Source: https://www.mediawiki.org/wiki/Manual:Hooks/CategoryAfterPageRemoved
         * @param $category: Category that page was removed from
	 * @param $wikiPage: WikiPage that was removed
	 * @param $id: Page ID - this should be the original deleted page ID,
         */
	public static function onCategoryAfterPageRemoved( $category, $wikiPage, $id ) {

		# Get title of the page and parent category the page was removed from
		# e.g.: onCategoryAfterPageRemoved Category:Pippo categoryName: Difesa_e_Sicurezza
                $pageRemovedTitle = $wikiPage->getTitle();	# e.g.: $pageRemovedTitle: Category:Pippo
                $categoryName = $category->getName();		# e.g.: $categoryName: Pluto
                $isCategory = str_starts_with($pageRemovedTitle, "Category:");
                
                # BreadCrumbs works only with Categories, the pages contained in a Category are automatically associated
                if($isCategory){
                        # Retrieves only current page name (category) and parent category name
                        $pageRemovedTitle = str_replace("Category:", "", $pageRemovedTitle);
                        $pageRemovedTitle = str_replace("_", " ", $pageRemovedTitle);
                        $categoryName = str_replace("_", " ", $categoryName);

			# Searches for breadcrubs containing the current page name (category) and removes them
			# If the current page (category) is removed from the hierarchy, its children should be
			# orphaned for this removes breadcrubs containing the current page name
                        if($category->getPage()->exists() && $category->getPage()->canExist()){
                                $updateClass = new BreadCrumbs2Update;
                                $updateClass->removeBreadCrumbs( $categoryName, $pageRemovedTitle);
                        }
                }
	}

	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ){
	}
}
