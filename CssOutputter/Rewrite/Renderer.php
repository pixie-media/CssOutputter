<?php

declare(strict_types=1);

namespace PixieMedia\CssOutputter\Rewrite;

use Magento\Framework\View\Page\Config\Renderer as OriginalRenderer;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Asset\GroupedCollection;
use Magento\Framework\View\Page\Config;
use Magento\Framework\View\Page\Config\Metadata\MsApplicationTileImage;

class Renderer extends OriginalRenderer
{
	
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
	
	/**
     * @var Minifier
     */
    protected $minifier;


    /**
     * @param ScopeConfigInterface $scopeConfig
	 * @param Config $pageConfig
     * @param \Magento\Framework\View\Asset\MergeService $assetMergeService
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Framework\Escaper $escaper
     * @param \Magento\Framework\Stdlib\StringUtils $string
     * @param \Psr\Log\LoggerInterface $logger
     * @param MsApplicationTileImage|null $msApplicationTileImage
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
		Repository $assetRepo,
		\Magento\Framework\Code\Minifier\Adapter\Css\CSSmin $minifier,
        Config $pageConfig,
        \Magento\Framework\View\Asset\MergeService $assetMergeService,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\Escaper $escaper,
        \Magento\Framework\Stdlib\StringUtils $string,
        \Psr\Log\LoggerInterface $logger,
        MsApplicationTileImage $msApplicationTileImage = null
    ) {
        $this->scopeConfig = $scopeConfig;
		$this->assetRepo = $assetRepo;
		$this->minifier = $minifier;
        parent::__construct(
            $pageConfig,
        	$assetMergeService,
        	$urlBuilder,
        	$escaper,
        	$string,
			$logger,
            $msApplicationTileImage
        );
    }
	
	/**
     * Returns rendered HTML for all Assets (CSS before)
     *
     * @param array $resultGroups
     *
     * @return string
     */
	public function renderAssets($resultGroups = [])
    {
		
        /** @var $group \Magento\Framework\View\Asset\PropertyGroup */
        foreach ($this->pageConfig->getAssetCollection()->getGroups() as $group) {
            $type = $group->getProperty(GroupedCollection::PROPERTY_CONTENT_TYPE);
            if (!isset($resultGroups[$type])) {
                $resultGroups[$type] = '';
            }
            $resultGroups[$type] .= $this->renderAssetGroup($group); 
        }
		
		// Prepare for onpage output
	    if($this->isEnabled()) {
			if(isset($resultGroups['css'])) {
				
				$url       = $this->getStaticUrl();
				$inlineCss = $this->minifier->minify(str_replace("..",$url,$resultGroups['css']));
				$resultGroups['css'] = '<style data-source="pm-css-converter">'.$inlineCss.'</style>';
			}
		}
        return implode('', $resultGroups);
    }


	protected function renderAssetHtml(\Magento\Framework\View\Asset\PropertyGroup $group)
    {
		
		if(!$this->isEnabled()) {
			return parent::renderAssetHtml($group);
		}
		
        $assets     = $this->processMerge($group->getAll(), $group);
        $attributes = $this->getGroupAttributes($group);
        $result     = '';
        $template   = '';
		
	
        try {
            /** @var $asset \Magento\Framework\View\Asset\AssetInterface */
            foreach ($assets as $asset) {
				
				if($group->getProperty(GroupedCollection::PROPERTY_CONTENT_TYPE) == 'css') {
					
					if($cssContent = strip_tags($asset->getContent())) {
						$result .= $cssContent;
					}
					
					
				} else {
					
					$template = $this->getAssetTemplate(
                    $group->getProperty(GroupedCollection::PROPERTY_CONTENT_TYPE),
                    $this->addDefaultAttributes($this->getAssetContentType($asset), $attributes)
					);
					$result .= sprintf($template, $asset->getUrl());
					
				}
				
                
            }
        } catch (LocalizedException $e) {
            $this->logger->critical($e);
            $result .= sprintf($template, $this->urlBuilder->getUrl('', ['_direct' => 'core/index/notFound']));
        }
		
        return $result;
    }
	
	/**
     * @return bool
     */
	public function isEnabled() {
		
		return $this->scopeConfig->getValue(
            'css_outputter/configuration/enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
	}
	
	/**
	* @return string
	*/
	public function getStaticUrl() {
		$baseUrl   = $path = $this->scopeConfig->getValue('web/secure/base_url', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$params    = array('_secure' => true);
		$staticUrl = $this->assetRepo->getUrlWithParams('', $params);
		return str_replace($baseUrl,"/",$staticUrl);
	}
	
}