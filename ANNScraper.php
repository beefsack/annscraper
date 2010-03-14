<?php

// Scrapers

class ANNScraper_PageScraper
{
	protected $_url;
	protected $_urlValues = array();
	protected $_requestUrl;
	protected $_response;
	protected $_data = array();
	protected $_searches = array();
	
	static public function fetch($class, array $urlValues = array())
	{
		if (!class_exists($class)) {
			throw new ANNScraper_ScraperClassNotFound();
		}
		$obj = new $class;
		if (!($obj instanceof ANNScraper_PageScraper)) {
			throw new ANNScraper_ObjectNotChildOfPageScraper();
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
			throw new ANNScraper_UrlNotSpecified();
		}
		$this->_requestUrl = $this->_url;
		foreach ($this->_urlValues as $key => $value) {
			$this->_requestUrl = str_replace('{{{'.$key.'}}}', $value, $this->_requestUrl);
		}
		$ch = curl_init($this->_requestUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$this->_response = curl_exec($ch);
		curl_close($ch);
		return true;
	}
	
	protected function _parseResponse()
	{
		$data = array();
		$data['request'] = array(
			'url' => $this->_requestUrl,
			'values' => $this->_urlValues,
		);
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
	
	public function registerSearch(ANNScraper_Search $search)
	{
		$this->_searches[] = $search;
		return $this;
	}
	
	public function getData()
	{
		return $this->_data;
	}
}

class ANNScraper_AnimePageScraper extends ANNScraper_PageScraper
{
	protected $_url = 'http://www.animenewsnetwork.com/encyclopedia/anime.php?id={{{id}}}';
	
	public function __construct()
	{
		$this->registerSearch(new ANNScraper_SearchAnimeTitles())
			->registerSearch(new ANNScraper_SearchAnimeGenres())
			->registerSearch(new ANNScraper_SearchAnimeThemes())
			->registerSearch(new ANNScraper_SearchAnimeRelated())
			->registerSearch(new ANNScraper_SearchAnimeVintage())
			->registerSearch(new ANNScraper_SearchAnimeStats())
		;
	}
}

// Searches

abstract class ANNScraper_Search
{
	protected $_name;
	
	abstract public function parse($data);
	
	public function getName()
	{
		if (!isset($this->_name)) {
			throw new ANNScraper_SearchNameNotSpecified();
		}
		return $this->_name;
	}
}

class ANNScraper_SearchAnimeTitles extends ANNScraper_Search
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

class ANNScraper_SearchAnimeGenres extends ANNScraper_Search
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

class ANNScraper_SearchAnimeThemes extends ANNScraper_Search
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

class ANNScraper_SearchAnimeRelated extends ANNScraper_Search
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

class ANNScraper_SearchAnimeVintage extends ANNScraper_Search
{
	protected $_name = 'vintage';
	
	public function parse($data)
	{
		$value = null;
		// Get vintage
		if (preg_match('/<STRONG>Vintage:<\/STRONG>[^<]*<SPAN>([^<]*)<\/SPAN>/s', $data, $matches)) {
			$value = $matches[1];
		}
		return $value;
	}
}

class ANNScraper_SearchAnimeStats extends ANNScraper_Search
{
	protected $_name = 'stats';
	
	public function parse($data)
	{
		$values = array();
		// Seen
		if (preg_match('/<B>Seen<\/B>[^<\d]*(\d*) users/s', $data, $matches)) {
			$values['seen'] = (int) $matches[1];
		}
		// Median rating
		if (preg_match('/<B>Median rating:<\/B>\s*([^<]*)\s*<BR>/s', $data, $matches)) {
			$values['medianrating'] = $matches[1];
		}
		// Arithmetic mean
		if (preg_match('/<B>Arithmetic mean:<\/B>\s*([\d\.]+)/s', $data, $matches)) {
			$values['arithmeticmean'] = (float) $matches[1];
		}
		// Weighted mean
		if (preg_match('/<B>Weighted mean:<\/B>\s*([\d\.]+)/s', $data, $matches)) {
			$values['weightedmean'] = (float) $matches[1];
		}
		// Bayesian
		if (preg_match('/<B>Bayesian estimate:<\/B>\s*([\d\.]+)/s', $data, $matches)) {
			$values['bayesian'] = (float) $matches[1];
		}
		// Rank
		if (preg_match('/<B>Bayesian estimate:<\/B>[^<]*rank: #([\d]*)/s', $data, $matches)) {
			$values['rank'] = (int) $matches[1];
		}
		return $values;
	}
}

// Exceptions

class ANNScraper_ScraperClassNotFound extends Exception {};
class ANNScraper_ObjectNotChildOfPageScraper extends Exception {};
class ANNScraper_UrlNotSpecified extends Exception {};
class ANNScraper_SearchNameNotSpecified extends Exception {};

// Main class

class ANNScraper
{
	public function fetchAnime($id)
	{
		return ANNScraper_PageScraper::fetch('ANNScraper_AnimePageScraper', array(
			'id' => $id,
		));
	}
}
