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
				$inlineCss = $this->minify_css(str_replace("..",$url,$resultGroups['css']));
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
	
	/**
     * @return string
     */
	public function minify_css($input) {
		if(trim($input) === "") return $input;
		return preg_replace(
			array(
				// Remove comment(s)
				'#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')|\/\*(?!\!)(?>.*?\*\/)|^\s*|\s*$#s',
				// Remove unused white-space(s)
				'#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/))|\s*+;\s*+(})\s*+|\s*+([*$~^|]?+=|[{};,>~]|\s(?![0-9\.])|!important\b)\s*+|([[(:])\s++|\s++([])])|\s++(:)\s*+(?!(?>[^{}"\']++|"(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')*+{)|^\s++|\s++\z|(\s)\s+#si',
				// Replace `0(cm|em|ex|in|mm|pc|pt|px|vh|vw|%)` with `0`
				'#(?<=[\s:])(0)(cm|em|ex|in|mm|pc|pt|px|vh|vw|%)#si',
				// Replace `:0 0 0 0` with `:0`
				'#:(0\s+0|0\s+0\s+0\s+0)(?=[;\}]|\!important)#i',
				// Replace `background-position:0` with `background-position:0 0`
				'#(background-position):0(?=[;\}])#si',
				// Replace `0.6` with `.6`, but only when preceded by `:`, `,`, `-` or a white-space
				'#(?<=[\s:,\-])0+\.(\d+)#s',
				// Minify string value
				'#(\/\*(?>.*?\*\/))|(?<!content\:)([\'"])([a-z_][a-z0-9\-_]*?)\2(?=[\s\{\}\];,])#si',
				'#(\/\*(?>.*?\*\/))|(\burl\()([\'"])([^\s]+?)\3(\))#si',
				// Minify HEX color code
				'#(?<=[\s:,\-]\#)([a-f0-6]+)\1([a-f0-6]+)\2([a-f0-6]+)\3#i',
				// Replace `(border|outline):none` with `(border|outline):0`
				'#(?<=[\{;])(border|outline):none(?=[;\}\!])#',
				// Remove empty selector(s)
				'#(\/\*(?>.*?\*\/))|(^|[\{\}])(?:[^\s\{\}]+)\{\}#s'
			),
			array(
				'$1',
				'$1$2$3$4$5$6$7',
				'$1',
				':0',
				'$1:0 0',
				'.$1',
				'$1$3',
				'$1$2$4$5',
				'$1$2$3',
				'$1:0',
				'$1$2'
			),
		$input);
	}
}