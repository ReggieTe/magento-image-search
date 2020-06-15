<?php 

namespace Future\ImageSearch\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;
use Future\ImageSearch\Helper\Data;
use Future\ImageSearch\Helper\Storage;
use Future\ImageSearch\Helper\Values; 


class ImageSearchProductSaveObserver implements ObserverInterface
{    
    protected $dataHelper;

    protected $storageHelper;

    protected $scopeConfig;

    protected $projectValue;

    protected $storeManager;

    protected $logger;

    public function __construct(
        Data $helper,
        Storage $storage,
        ScopeConfigInterface $scopeConfig,
        Values $projectValue ,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {        
        $this->dataHelper = $helper;
        $this->storageHelper = $storage;
        $this->scopeConfig = $scopeConfig;
        $this->projectValue = $projectValue;
        $this->logger = $logger;
    }


    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        try{
                if ($this->projectValue->getModuleEnable()) {

                    $product = $observer->getProduct();

                    if($product){

                        $productSku = $product->getSku();
                        $productDisplayName = $product->getName();
                        $productId = $product->getSku();
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
                            !empty($productDisplayName)?$this->dataHelper->productUpdate($productId,'display_name',$productDisplayName):'';                            
                            !empty($productDescription)?$this->dataHelper->productUpdate($productId,'discription',$productDescription):'';
                            !empty($productCategory)?$this->dataHelper->productUpdate($productId,'category',$productCategory):''; 
                            $this->uploadImages($productImageSourceOrLocation,$productId,$productSku);                  

                        }
                    }            
                }
            } catch (\Exception $e) {
                $this->logger->critical('Observer Error message', ['exception' => $e]);      
                throw $e;    
            }

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