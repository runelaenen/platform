const { Component, Mixin, Utils } = Shopware;

Component.extend('sw-cms-el-manufacturer-logo', 'sw-cms-el-image', {
    mixins: [
        Mixin.getByName('cms-element')
    ],

    computed: {
        isProductPage() {
            return Utils.get(this.cmsPageState, 'currentPage.type', '') === 'product_detail';
        },

        styles() {
            const { displayMode, minHeight, verticalAlign } = this.element.config;
            return {
                'max-width': '180px',
                'min-height': displayMode.value === 'cover' && minHeight.value && minHeight.value !== 0
                    ? minHeight.value
                    : '40px',
                'align-self': verticalAlign.value || null
            };
        }
    },

    methods: {
        createdComponent() {
            this.initElementConfig('manufacturer-logo');
            this.initElementData('manufacturer-logo');

            if (this.isProductPage && !Utils.get(this.element, 'translated.config.media')) {
                this.element.config.media.source = 'mapped';
                this.element.config.media.value = 'product.manufacturer.media';
            }
        }
    }
});
