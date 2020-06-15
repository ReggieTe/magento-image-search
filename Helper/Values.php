<?php

namespace Future\ImageSearch\Helper;


use Magento\Framework\App\Config\ScopeConfigInterface;  

 

class Values 
{
    public $scopeConfig;


        public function __construct(
            ScopeConfigInterface $scopeConfigInterface
        )
        {          
            $this->scopeConfig = $scopeConfigInterface;            
        }

      public function getGoogleApiKey()
      {
        return $this->getProjectBasePath()."/".$this->scopeConfig->getValue('imagesearch/general/google_api');
      }

      public function getProjectId()
      {
        return $this->scopeConfig->getValue('imagesearch/general/project_id');
      }

      public function getBucketName()
      {
        return $this->scopeConfig->getValue('imagesearch/general/project_bucket_name');
      }

      public function getProjectLocation()
      {
        return $this->scopeConfig->getValue('imagesearch/general/project_location');
      }

      public function getModuleEnable()
      {
        return $this->scopeConfig->getValue('imagesearch/general/module_enable');
      }
      public function getShopProductCategory()
      {
        return $this->scopeConfig->getValue('imagesearch/general/shop_product_category');
      }
        
      public function getProjectSetId()
      {
        return $this->scopeConfig->getValue('imagesearch/general/project_set_id');
      }

      public function getProjectMediaPath()
      {
        return $this->scopeConfig->getValue('imagesearch/general/project_media_path');
      }

      public function getProjectConfidenceScore()
      {
        return $this->scopeConfig->getValue('imagesearch/general/project_confidence_score');
      }

      public function getProjectBasePath()
      {
        return $this->scopeConfig->getValue('imagesearch/general/project_base_path');
      }      
      
}


