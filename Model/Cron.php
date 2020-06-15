<?php

namespace Future\ImageSearch\Model;

use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\FilterBuilder;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Psr\Log\LoggerInterface;


use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;
use Future\ImageSearch\Helper\Data;
use Future\ImageSearch\Helper\Storage;
use Future\ImageSearch\Helper\Values; 




class Cron
{

    protected $dataHelper;

    protected $storageHelper;

    protected $scopeConfig;

    protected $projectValue;

    protected $storeManager;

    protected $logger;

    protected $productCollection;

    public function __construct(
        Data $helper,
        Storage $storage,
        ScopeConfigInterface $scopeConfig,
        Values $projectValue ,
        StoreManagerInterface $storeManager,
        ProductRepository $productRepository,       
        SearchCriteriaInterface $criteria,
        FilterGroup $filterGroup,
        FilterBuilder $filterBuilder,
        Status $productStatus,
        Visibility $productVisibility,
        LoggerInterface $logger,
        CollectionFactory $productCollection
    ) {
        $this->productRepository = $productRepository;
        $this->searchCriteria = $criteria;
        $this->filterGroup = $filterGroup;
        $this->filterBuilder = $filterBuilder;
        $this->productStatus = $productStatus;
        $this->productVisibility = $productVisibility;
        $this->dataHelper = $helper;
        $this->storageHelper = $storage;
        $this->scopeConfig = $scopeConfig;
        $this->projectValue = $projectValue;
        $this->logger = $logger;
        $this->productCollection=$productCollection;
    }
    
  

    /**
     * Process the notification
     * @return void
     */
    public function processProducts()
    {
        try {
            $products = $this->getAllProducts();
            foreach($products as $product)
            {            
                    if($product){
        
                        $productSku = $product->getSku();
                        $productDisplayName = $product->getName();
                        $productId = $product->getId();
                        $productImageSourceOrLocation =  $this->projectValue->getProjectMediaPath().$product->getImage();
                        
                        $productCategory =  $this->projectValue->getShopProductCategory();                
                        $productSetId= $this->projectValue->getProjectSetId();
                        $productSetLists=$this->dataHelper->productSetList();
                        //Create Product set if it doesn't exist
                        (!$this->dataHelper->checkIfExist($productSetLists,$productSetId))? $this->dataHelper->productSetCreate($productSetId,"Magento Store"):'';
                                        
                        $productLists=$this->dataHelper->productList();
        
                        if (!$this->dataHelper->checkIfExist($productLists,$productId)) {
                            //Save a new product on the cloud
                            $this->dataHelper->productCreate($productId, $productDisplayName, $productCategory);
                            //Add product to set
                            $this->dataHelper->productSetAddProduct($productId, $productSetId);                  
                        } 
                        else
                        {                    
                            //Update Product
                            $productDescription = $product->getDescription();
                            $this->dataHelper->productUpdate($productId,'display_name',$productDisplayName);
                            $this->dataHelper->productUpdate($productId,'discription',$productDescription);
                            $this->dataHelper->productUpdate($productId,'category',$productCategory); 
                            $this->uploadImages($productImageSourceOrLocation,$productId,$productSku);                  
        
                        }
                    }            
                } 
        } catch (\Exception $e) {
            $this->logger->critical('Process Product Error message', ['exception' => $e]); 
            throw $e;          
        }
    }

    public function cleanupCloud()
    {
       try {
                $products = $this->dataHelper->productList();
                foreach($products as $product)
                {            
                    $productId=$product['id'];

                    $collection = $this->productCollection->create()->addAttributeToSelect('*')
                    ->addAttributeToFilter('sku',$productId)->load();
                    
                        if(count($collection)==0){                            
                                $this->dataHelper->productDelete($productId);
                                $images = $this->storageHelper->listObjectsWithPrefix("$productId/");
                                foreach($images as $image)
                                {
                                    $this->storageHelper->deleteOobject($image);
                                }  
                            }                              
                }  
        } catch (\Exception $e) {
            $this->logger->critical('Cloud Clean Up Error message', ['exception' => $e]);    
            throw $e;         
        }

    }


      /**
     * @return \Magento\Cms\Model\Block|null
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getAllProducts()
    {
    
        $this->filterGroup->setFilters([
            $this->filterBuilder
                ->setField('status')
                ->setConditionType('in')
                ->setValue($this->productStatus->getVisibleStatusIds())
                ->create(),
            $this->filterBuilder
                ->setField('visibility')
                ->setConditionType('in')
                ->setValue($this->productVisibility->getVisibleInSiteIds())
                ->create(),
        ]);
    
        $this->searchCriteria->setFilterGroups([$this->filterGroup]);
        $products = $this->productRepository->getList($this->searchCriteria);
        $productItems = $products->getItems();
    
        return $productItems;
    }

    public function uploadImages($productImageSourceOrLocation,$productId,$productSku)
    {
        if(is_file($productImageSourceOrLocation))
        {
            //Upload image to buck and return gcsUri
            $productImageNewNamePath = $productId."/".$productSku."-".rand(0,10000).".png";
            //save product to cloud
            $gcsUri = $this->storageHelper->uploadObject($productImageNewNamePath, $productImageSourceOrLocation);
            //Create & save product reference image
            $referenceImageId=$productSku.$productId."-".rand(0,10000);
            $this->dataHelper->productImageCreate($productId, $referenceImageId, $gcsUri);
        }
    }

 
}
