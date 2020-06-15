<?php

namespace Future\ImageSearch\Controller\Result;

use Magento\Catalog\Model\Layer\Resolver;
use Magento\Catalog\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Search\Model\QueryFactory;
use Magento\Search\Model\PopularSearchTerms;
use Magento\Framework\Filesystem;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\CatalogSearch\Controller\Result\Index as CoreResult;
use Future\ImageSearch\Helper\Data;
use Future\ImageSearch\Helper\Storage;
use Future\ImageSearch\Helper\Values; 
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Psr\Log\LoggerInterface;


class Index extends CoreResult
{

     /**
     * Catalog session
     *
     * @var Session
     */
    protected $_catalogSession;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var QueryFactory
     */
    private $_queryFactory;

    /**
     * Catalog Layer Resolver
     *
     * @var Resolver
     */
    private $layerResolver;


    protected $fileSystem;

    protected $fileUploaderFactory;

    protected $dataHelper;

    protected $storageHelper;

    protected $scopeConfig;

    protected $projectValue;

    protected $productCollection;

    protected $logger;

                
    public function __construct(
        Context $context,
        Session $catalogSession,
        StoreManagerInterface $storeManager,
        QueryFactory $queryFactory,
        Resolver $layerResolver,
        Filesystem $fileSystem,
        UploaderFactory $fileUploaderFactory,        
        ScopeConfigInterface $scopeConfig,
        Data $helper,
        Storage $storage,
        Values $projectValue,
        CollectionFactory $productCollection,
        LoggerInterface $logger
    ) {
        $this->fileUploaderFactory = $fileUploaderFactory;
        $this->fileSystem = $fileSystem;
        $this->dataHelper = $helper;
        $this->storageHelper = $storage;
        $this->scopeConfig = $scopeConfig;
        $this->projectValue = $projectValue;
        $this->productCollection = $productCollection;
        $this->logger = $logger;

        $this->_storeManager = $storeManager;
        $this->_catalogSession = $catalogSession;
        $this->_queryFactory = $queryFactory;
        $this->layerResolver = $layerResolver;


        parent::__construct($context,$catalogSession,$storeManager,$queryFactory,$layerResolver);
    }

   

    /**
     * Display search result
     *
     * @return void
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    { 
           
        $this->ImageSearch();

        $this->layerResolver->create(Resolver::CATALOG_LAYER_SEARCH);
        /* @var $query \Magento\Search\Model\Query */
        $query = $this->_queryFactory->get();

        $storeId = $this->_storeManager->getStore()->getId();
        $query->setStoreId($storeId);
        $queryText = $query->getQueryText();

        if ($queryText != '') {
            $catalogSearchHelper = $this->_objectManager->get(\Magento\CatalogSearch\Helper\Data::class);

            $getAdditionalRequestParameters = $this->getRequest()->getParams();
           
            unset($getAdditionalRequestParameters[QueryFactory::QUERY_VAR_NAME]);

            $handles = null;
            if ($query->getNumResults() == 0) {
                $this->_view->getPage()->initLayout();
                $handles = $this->_view->getLayout()->getUpdate()->getHandles();
                $handles[] = static::DEFAULT_NO_RESULT_HANDLE;
            }
       
            if (empty($getAdditionalRequestParameters) &&
                $this->_objectManager->get(PopularSearchTerms::class)->isCacheable($queryText, $storeId)
            ) {
                $this->getCacheableResult($catalogSearchHelper, $query, $handles);
            } else {
                $this->getNotCacheableResult($catalogSearchHelper, $query, $handles);
            }
        } else {
            $this->getResponse()->setRedirect($this->_redirect->getRedirectUrl());
        }
    }




    /**
     * Return cacheable result
     *
     * @param \Magento\CatalogSearch\Helper\Data $catalogSearchHelper
     * @param \Magento\Search\Model\Query $query
     * @param array $handles
     * @return void
     */
    private function getCacheableResult($catalogSearchHelper, $query, $handles)
    { 
        if (!$catalogSearchHelper->isMinQueryLength()) {
            $redirect = $query->getRedirect();
            if ($redirect && $this->_url->getCurrentUrl() !== $redirect) {
                $this->getResponse()->setRedirect($redirect);
                return;
            }
        }

        $catalogSearchHelper->checkNotes();

        $this->_view->loadLayout($handles);
        $this->_view->renderLayout();
    }

    /**
     * Return not cacheable result
     *
     * @param \Magento\CatalogSearch\Helper\Data $catalogSearchHelper
     * @param \Magento\Search\Model\Query $query
     * @param array $handles
     * @return void
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getNotCacheableResult($catalogSearchHelper, $query, $handles)
    {
        if ($catalogSearchHelper->isMinQueryLength()) {
            $query->setId(0)->setIsActive(1)->setIsProcessed(1);
        } else {
            $query->saveIncrementalPopularity();
            $redirect = $query->getRedirect();
            if ($redirect && $this->_url->getCurrentUrl() !== $redirect) {
                $this->getResponse()->setRedirect($redirect);
                return;
            }
        }

        $catalogSearchHelper->checkNotes();

        $this->_view->loadLayout($handles);
        $this->getResponse()->setNoCacheHeaders();
        $this->_view->renderLayout();
    }

    protected function ImageSearch()
    {
        try{
                if(!empty($_FILES)) 
                {
                    $products=$this->cloudSearch();   
                    $paramToUpdateRequest ="Image Search Nothing Found";
                    if (is_array(current($products))) {
                        $itemsToSearch = array();
                        $systemConfidenceStore = $this->projectValue->getProjectConfidenceScore();
                        foreach ($products as $product) {
                            if ($product['score'] >= $systemConfidenceStore) {
                                array_push($itemsToSearch, $product['name']);
                                array_push($itemsToSearch, $product['entity_id']);
                            }
                        }

                        $paramToUpdateRequest = implode(",", $itemsToSearch);
                    }  
                    $this->getRequest()->setParam('q',$paramToUpdateRequest);
                }
        } catch (Exception $e) {
            $this->logger->critical('Cloud Search Error message', ['exception' => $e]);        
            throw $e;   
        }
    }

    protected function cloudSearch()
    {
        try{
        $productIdAndCOnfidenceScore=array("error"=>"Nothing Found");
          if ($this->projectValue->getModuleEnable()) {
              
                $uploadResult = $this->upload();
                $productImageToSearchOnCloud = $uploadResult['path'].$uploadResult['file'];
                $productCategory =  $this->projectValue->getShopProductCategory();
                $productSetId= $this->projectValue->getProjectSetId(); 
                $productSetLists=$this->dataHelper->productSetList();
                        
                !$this->dataHelper->checkIfExist($productSetLists,$productSetId)? $this->dataHelper->productSetCreate($productSetId,"Magento Store"):'';

                $productSearchFromCloudResults = $this->dataHelper->productSearchSimilar($productSetId, $productCategory, $productImageToSearchOnCloud,"") ;
                
                if(method_exists ( $productSearchFromCloudResults , "getDetails" ))
                {
                    return( $productSearchFromCloudResults->getCode()==5)? $productIdAndCOnfidenceScore : '';                  
                }                
                else
                {
                    foreach ($productSearchFromCloudResults as $productSearchFromCloudResult) {
                        array_push($productIdAndCOnfidenceScore, array("entity_id" => $productSearchFromCloudResult['id'],"name" => $productSearchFromCloudResult['display_name'],"score" =>$productSearchFromCloudResult['confidence_score']));
                    }
                    return $productIdAndCOnfidenceScore; 
                }
            }
            return $productIdAndCOnfidenceScore; 
        } catch (Exception $e) {
            $this->logger->critical('Cloud Search Error message', ['exception' => $e]);        
            throw $e;   
        }
    }

    protected function upload()
    {                
                try {
                    $result = array();  
                    $uploader = $this->fileUploaderFactory->create(['fileId' => 'image']);      
                    $uploader->setAllowedExtensions(['jpg', 'jpeg', 'gif', 'png']);          
                    $uploader->setAllowRenameFiles(false);          
                    $uploader->setFilesDispersion(false);     
                    $path = $this->fileSystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath();
                    $result = $uploader->save($path);  
                    return $result;
                } catch (Exception $e) {
                    $this->logger->critical('Upload Image Error message', ['exception' => $e]);        
                    throw $e;   
                }       
    }


}
