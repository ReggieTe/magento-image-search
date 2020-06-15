define([
    "jquery", "Magento_Ui/js/modal/modal"
], function($){
    var SearchModal = {
        initModal: function(config, element) {
            $target = $(config.target);
            $target.modal();
            $element = $(element);
            $element.click(function() {
                $target.modal('openModal');
            });
        }
    };
    return {
        'search-modal': SearchModal.initModal
    };
}
);