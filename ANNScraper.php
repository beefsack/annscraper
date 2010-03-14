<?php

// Scrapers

class PageScraper
{
	protected $_url;
	protected $_urlValues = array();
	protected $_response;
	protected $_data = array();
	protected $_searches = array();
	
	static public function fetch($class, array $urlValues = array())
	{
		if (!class_exists($class)) {
			throw new ScraperClassNotFound();
		}
		$obj = new $class;
		if (!($obj instanceof PageScraper)) {
			throw new ObjectNotChildOfPageScraper();
		}
		return $obj->setValues($urlValues)->scrape()->getData();
	}
	
	public function scrape()
	{
		$this->_requestPage();
		$this->_parseResponse();
		return $this;
	}
	
	protected function _requestPage()
	{
		if (!isset($this->_url)) {
			throw new UrlNotSpecified();
		}
		$url = $this->_url;
		foreach ($this->_urlValues as $key => $value) {
			$url = str_replace('{{{'.$key.'}}}', $value, $url);
		}
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$this->_response = curl_exec($ch);
		curl_close($ch);
		return true;
	}
	
	protected function _parseResponse()
	{
		$data = array();
		foreach ($this->_searches as $search) {
			$data = array_merge($data, array(
				$search->getName() => $search->parse($this->_response)
			));
		}
		$this->_data = $data;
	}
	
	protected function setValues(array $values = array())
	{
		$this->_urlValues = array_merge($this->_urlValues, $values);
		return $this;
	}
	
	public function registerSearch(Search $search)
	{
		$this->_searches[] = $search;
		return $this;
	}
	
	public function getData()
	{
		return $this->_data;
	}
}

class AnimePageScraper extends PageScraper
{
	protected $_url = 'http://www.animenewsnetwork.com/encyclopedia/anime.php?id={{{id}}}';
	
	public function __construct()
	{
		$this->registerSearch(new SearchAnimeTitles())
			->registerSearch(new SearchAnimeGenres())
			->registerSearch(new SearchAnimeThemes())
			->registerSearch(new SearchAnimeRelated())
		;
	}
}

// Searches

abstract class Search
{
	protected $_name;
	
	abstract public function parse($data);
	
	public function getName()
	{
		if (!isset($this->_name)) {
			throw new SearchNameNotSpecified();
		}
		return $this->_name;
	}
}

class SearchAnimeTitles extends Search
{
	protected $_name = 'title';
	
	public function parse($data)
	{
		$values = array();
		// Get english title
		if (preg_match('/<h1 id="page_header">(.*?)<\/h1>/', $data, $matches)) {
			$values['English'] = $matches[1];
		}
		// Get other titles
		if (preg_match('/<STRONG>Alternative title:<\/STRONG>(.*?)<DIV CLASS="encyc/s', $data, $matches)) {
			if (preg_match_all('/<DIV CLASS="tab">(.*?)\s*\((.*?)\)<\/DIV>/', $matches[1], $titles)) {
				foreach ($titles[1] as $key => $name) {
					$values[$titles[2][$key]] = $name;
				}
			}
		}
		return $values;
	}
}

class SearchAnimeGenres extends Search
{
	protected $_name = 'genre';
	
	public function parse($data)
	{
		$values = array();
		// Get genres
		if (preg_match('/<STRONG>Genres:<\/STRONG>(.*?)<DIV CLASS="encyc/s', $data, $matches)) {
			if (preg_match_all('/<SPAN><a href="[^"]*g=([^&"]*)[^>]*>([^<]*)<\/a><\/SPAN>/', $matches[1], $genres)) {
				foreach ($genres[2] as $key => $name) {
					$values[$genres[1][$key]] = $name;
				}
			}
		}
		return $values;
	}
}

class SearchAnimeThemes extends Search
{
	protected $_name = 'theme';
	
	public function parse($data)
	{
		$values = array();
		// Get genres
		if (preg_match('/<STRONG>Themes:<\/STRONG> (.*?)<DIV CLASS="encyc/s', $data, $matches)) {
			if (preg_match_all('/<SPAN><a href="[^"]*th=([^&"]*)[^>]*>([^<]*)<\/a><\/SPAN>/', $matches[1], $themes)) {
				foreach ($themes[2] as $key => $name) {
					$values[$themes[1][$key]] = $name;
				}
			}
		}
		return $values;
	}
}

class SearchAnimeRelated extends Search
{
	protected $_name = 'related';
	
	public function parse($data)
	{
		$values = array();
		// Get main relation
		if (preg_match('/<\/DIV><SMALL>\[ (.*?) <A  HREF="[^"]*\/([^"\/]*?)\.php[^"]*id=([^"&]*)">([^<]*)<\/A> \]<BR><BR><\/SMALL>/s', $data, $matches)) {
			$values[$matches[3]] = array(
				'relation' => $matches[1],
				'type' => $matches[2],
				'name' => $matches[4],
			);
		}
		// Get other relations
		if (preg_match('/<B>Related anime:<\/B>(.*?)<DIV CLASS="encyc/s', $data, $matches)) {
			if (preg_match_all('/<A  HREF="[^"]*\/([^"\/]*?)\.php[^"]*id=([^"&]*)">([^<]*)<\/A>[^\(]*(\(([^\)]*)\))?<BR>/s', $matches[1], $relations)) {
				foreach ($relations[2] as $key => $id) {
					$values[$id] = array(
						'relation' => $relations[5][$key],
						'type' => $relations[1][$key],
						'name' => $relations[3][$key],
					);
				}
			}
		}
		return $values;
	}
}

// Exceptions

class ScraperClassNotFound extends Exception {};
class ObjectNotChildOfPageScraper extends Exception {};
class UrlNotSpecified extends Exception {};
class SearchNameNotSpecified extends Exception {};

// Main class

class ANNScraper
{
	public function fetchAnime($id)
	{
		return PageScraper::fetch('AnimePageScraper', array(
			'id' => $id,
		));
	}
}

// Testing

$scraper = new ANNScraper();
var_dump($scraper->fetchAnime(7809));