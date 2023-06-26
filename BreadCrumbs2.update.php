<?php
/*
 * This file is part of Extension:BreadCrumbs2
 *
 * This class provides the methods to add and remove breadcrums from 
 * the page MediaWiki:Breadcrums
 */

use MediaWiki\MediaWikiServices;

class BreadCrumbs2Update {

	/*
		edit.php

		MediaWiki API Demos
		Demo of `Edit` module: POST request to edit a page
		MIT license
	*/

	private $endPoint = "";
	# actions to edit a page
	private $alltextAction = 1;
	private $prependtextAction = 2;
	private $appendtextAction = 3;
	private $domain = "";

	public function __construct() {
		global $wgServer, $wgScriptPath;
		$this->domain = $wgScriptPath;
		$this->endPoint = $wgServer.$this->domain."/api.php";
	}

	# Add a breadcrumb to the page MediaWiki:BreadCrumnbs
	public function addBreadCrumb( $categories){

		# This version assums only one parent category for a page, 
		# consequently only one breadcrumbs for a parent category
		if(empty($categories)){
			return;
		}
		$category = $categories[0] ?? null;

		# Get the "permission to add a breadcrumb"
		$login_Token = $this->getLoginToken();
                $this->loginRequest( $login_Token );
		$csrf_Token_info = $this->getCSRFToken();
		$csrf_Token = $csrf_Token_info["query"]["tokens"]["csrftoken"];
		$starttimestamp = $csrf_Token_info["curtimestamp"];

		# Add breadcrumb to the table
		$results = $this->buildTextToAdd($category);
		$text_to_add = $results["wikitext"];
		$this->editRequest($starttimestamp, $csrf_Token, $text_to_add);

		return $results["html"];
	}	

	public function removeBreadCrumbs( $categoryName, $pageRemovedTitle){
		# Get the "permission to remove a breadcrumbs"
		$login_Token = $this->getLoginToken(); 
                $this->loginRequest( $login_Token ); 
		$csrf_Token_info = $this->getCSRFToken(); 
		$csrf_Token = $csrf_Token_info["query"]["tokens"]["csrftoken"];
                $starttimestamp = $csrf_Token_info["curtimestamp"];

		# Delete the breadcrumbs from table
		$text_cleaned = $this->cleanText($pageRemovedTitle);
                $this->editRequest($starttimestamp, $csrf_Token, $text_cleaned, 1);
        }

	// Get request to fetch login token
	private function getLoginToken() {

		$params1 = [
			"action" => "query",
			"meta" => "tokens",
			"type" => "login",
			"format" => "json"
		];

		$url = $this->endPoint . "?" . http_build_query( $params1 );

		$ch = curl_init( $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_COOKIEJAR, "cookie.txt" );
		curl_setopt( $ch, CURLOPT_COOKIEFILE, "cookie.txt" );

		$output = curl_exec( $ch );
		curl_close( $ch );

		$result = json_decode( $output, true );
		return $result["query"]["tokens"]["logintoken"];
	}

	// Post request to log in. Use of main account for login is not
	// supported. Obtain credentials via Special:BotPasswords
	// (https://www.mediawiki.org/wiki/Special:BotPasswords) for lgname & lgpassword
	private function loginRequest( $logintoken ) {

		$config = MediaWikiServices::getInstance()->getMainConfig();
		$bot = $config->get('Bot');
		$bot_key = $config->get('BotKey');
		
		$params2 = [
			"action" => "login",
			"lgname" => $bot,
			"lgpassword" => $bot_key,
			"lgtoken" => $logintoken,
			"format" => "json"
		];

		$ch = curl_init();

		curl_setopt( $ch, CURLOPT_URL, $this->endPoint );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $params2 ) );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_COOKIEJAR, "cookie.txt" );
		curl_setopt( $ch, CURLOPT_COOKIEFILE, "cookie.txt" );

		$output = curl_exec( $ch );
		curl_close( $ch );

	}

	// Get request to fetch CSRF token
	private function getCSRFToken() {

		$params3 = [
			"action" => "query",
			"format" => "json",
			"curtimestamp" => true,
			"meta" => "tokens"
		];

		$url = $this->endPoint . "?" . http_build_query( $params3 );

		$ch = curl_init( $url );

		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_COOKIEJAR, "cookie.txt" );
		curl_setopt( $ch, CURLOPT_COOKIEFILE, "cookie.txt" );

		$output = curl_exec( $ch );
		curl_close( $ch );

		$result = json_decode( $output, true );

		return $result;
	}

	# Find out the hierarchy of the page in input and create the breadcrumb
	private function buildTextToAdd($firstCategoryInPage){

		$risultato = "";
		$risultato_html = "";
		$father_tmp = "";
		$config = MediaWikiServices::getInstance()->getMainConfig();

		# include or not include the oldest parent category in a breadcrumb 
		$hide_root = $config->get( 'BreadCrumbs2RelationshipHideRoot' );

		# Mediawiki replaces spaces in the title of a page with the character underscore 
		# before inserting it in the database
                $firstCategoryInPage = str_replace(" ", "_", $firstCategoryInPage ?? '');

		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		# It needs DB_PRIMARY, even if it is a read, because CTE create a temporary view
		$dbr = $lb->getConnection( DB_PRIMARY );

		// Common Table Expressions
		$sql_cte = "with recursive ancestors as (select * from cat_relationship where child='".$firstCategoryInPage."' union select f.* from cat_relationship as f, ancestors as a where f.child = a.father) select * from ancestors;";
		$res = $dbr->query($sql_cte);

		# Create the breadcrumb
		$row= $res->fetchRow();
                while($row){
                        $father_db_tmp = $row['father'];
                        $child_db_tmp = $row['child'];
			$child_tmp = str_replace("_", " ", $child_db_tmp);
			$risultato = "[[:Category:".$child_tmp."|".$child_tmp."]] > ".$risultato;
			$risultato_html = '<a href="'.$this->domain.'/index.php/Category:'.$child_db_tmp.'" title="Category:'.$child_tmp.'">'.$child_tmp."</a> &gt; ".$risultato_html;
                        $row= $res->fetchRow();
                }
		
		if(!empty($risultato)){
			// add (or not) the oldest parent category
			if(!$hide_root){
				$father_tmp = str_replace("_", " ", $father_db_tmp);
				$risultato = "[[:Category:".$father_tmp."|".$father_tmp."]] > ".$risultato;
				$risultato_html = '<a href="'.$this->domain.'/index.php/Category:'.$father_db_tmp.'" title="Category:'.$father_tmp.'">'.$father_tmp."</a> &gt; ".$risultato_html;
			}
			# Replace the underscores with the spaces to restore the original name
			$firstCategoryInPage = str_replace("_", " ", $firstCategoryInPage);
			# Add the name of the current category to the head
			$risultato = "* ".$firstCategoryInPage." @ " . $risultato . "\r\n";
		}
		
		$array_risulati = [ "wikitext" => $risultato, "html" => $risultato_html ];

		# return the breadcrums (or empty)
		return $array_risulati;
	}

	# Get the MediaWiki:Breadcrumbs page content and remove the breadcrumbs containg the category in input
	private function cleanText($pageRemovedTitle){
                $paramsCheckExist = [
                        "action" => "query",
                        "prop" => "revisions",
                        "titles" => "MediaWiki:Breadcrumbs",
                        "rvprop" => "content",
                        "rvslots" => "main",
                        "formatversion" => "2",
                        "format" => "json"
                ];

                $url = $this->endPoint . "?" . http_build_query( $paramsCheckExist );

                $ch = curl_init( $url );
                curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
                curl_setopt( $ch, CURLOPT_COOKIEJAR, "cookie.txt" );
                curl_setopt( $ch, CURLOPT_COOKIEFILE, "cookie.txt" );
		$output = curl_exec( $ch );
		$result = json_decode( $output, true );
                curl_close( $ch );

		$new_page_content ="";
		
		# Get the page content (containing the breadcrumbs)
		$pageContent = $result["query"]["pages"][0]["revisions"][0]["slots"]["main"]["content"];
		# Split the page content in lines
		$breadcrumbs = preg_split('/\n|\r\n?/', $pageContent);
		foreach ($breadcrumbs as &$breadcrumb)
		{	
			# Keep the breadcrumb that not contains the current category (adds it to the new page content) 
                        if (!str_contains($breadcrumb, "[[:Category:$pageRemovedTitle|$pageRemovedTitle]] >")){
                                $new_page_content .= $breadcrumb."\r\n";
                        }
                }

		# The "new page content" (It’s not on the page yet)
		return $new_page_content;
        }



	# Update the MediaWiki:Breadcrumbs page content
	private function editRequest( $starttimestamp, $csrftoken, $text, $actionOnText = 2 ) {

		# Only if there an update
		if(!empty($text)){

			# basetimestamp and starttimestamp parameters should avoid writing conflicts 
			# between contemporary writes
			$paramsEditReq = [
				"action" => "edit",
				"title" => "MediaWiki:Breadcrumbs",
				"token" => $csrftoken,
				"format" => "json",
				"contentformat" => "text/x-wiki",
				"contentmodel" => "wikitext",
				"basetimestamp" => $this->getPageTimestamp(),
			        "starttimestamp" => $starttimestamp
			];

		
			if($actionOnText == 2){
				$paramsEditReq["prependtext"] = $text;
			}
			else if($actionOnText == 1){
				$paramsEditReq["text"] = $text;
			}
			else { // if ($appendtextAction){
				$paramsEditReq["appendtext"] = $text;
			}

			$ch = curl_init();

			curl_setopt( $ch, CURLOPT_URL, $this->endPoint );
			curl_setopt( $ch, CURLOPT_POST, true );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $paramsEditReq ) );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_COOKIEJAR, "cookie.txt" );
			curl_setopt( $ch, CURLOPT_COOKIEFILE, "cookie.txt" );
	
			$output = curl_exec( $ch );
			curl_close( $ch );
			//echo ( $output );
		}
	}

	# Get last revision timestamp of the MediaWiki:Breadcrumbs page
	private function getPageTimestamp(){

		$paramsTimestamp = [
                                "action" => "query",
                                "titles" => "MediaWiki:Breadcrumbs",
                                "prop" => "revisions",
                                "rvslots" => "main",
				"rvprop" => "timestamp",
				"formatversion" => "2",
				"format" => "json"
                        ];

                        $ch = curl_init();

                        curl_setopt( $ch, CURLOPT_URL, $this->endPoint );
                        curl_setopt( $ch, CURLOPT_POST, true );
                        curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $paramsTimestamp) );
                        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
                        curl_setopt( $ch, CURLOPT_COOKIEJAR, "cookie.txt" );
                        curl_setopt( $ch, CURLOPT_COOKIEFILE, "cookie.txt" );

			$output = curl_exec( $ch );
			$result = json_decode( $output, true );

			curl_close( $ch );
                        $page_timestamp = $result["query"]["pages"][0]["revisions"][0]["timestamp"];

			return $page_timestamp;
	} 
}
