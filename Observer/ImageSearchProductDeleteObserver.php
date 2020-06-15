<?php 

namespace Future\ImageSearch\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;
use Future\ImageSearch\Helper\Data;
use Future\ImageSearch\Helper\Storage;
use Future\ImageSearch\Helper\Values;


class ImageSearchProductDeleteObserver implements ObserverInterface
{    

    protected $dataHelper;

    protected $storageHelper;

    protected $scopeConfig;
  
    protected $projectValue;

    protected $logger;

    public function __construct(
        Data $helper,
        Storage $storage,
        ScopeConfigInterface $scopeConfig,
        Values $projectValue ,
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
       try{ //Execute if module is enabled
                if($this->projectValue->getModuleEnable()){
                    
                    $product = $observer->getProduct();
                    if($product){
                        
                        $productSku = $product->getSku(); 
                        $productId = $product->getId();       
                
                        if(count($this->dataHelper->productGet($productId))==0)
                        { 
                            //Remove product from set
                            $this->dataHelper-> productSetRemoveProduct($productId, $productSetId);
                            //Delete reference image
                            $referenceImageId=$productSku.$productId;
                            $this->dataHelper-> productImageDelete($productId, $referenceImageId);
                            //delete Product
                            $this->dataHelper-> productDelete($productId);
                            //delete product Image
                            $productImage = $productSku.".png";
                            $this->storageHelper-> deleteOobject($productImage);
                        } 
                    }
                    
                }  
        } catch (\Exception $e) {
            $this->logger->critical('Delete Observer Error message', ['exception' => $e]);        
            throw $e;     
        }    

    }   
}