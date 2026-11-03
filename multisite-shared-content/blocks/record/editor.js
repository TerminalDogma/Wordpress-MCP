(function (wp) {
    'use strict';
    wp.blocks.registerBlockType('msc/record', {
        edit: function (props) {
            return wp.element.createElement('div', wp.blockEditor.useBlockProps(),
                wp.element.createElement(wp.components.TextControl, {
                    label: 'Shared record UUID', value: props.attributes.uuid,
                    help: 'Copy the global identifier from Shared Content. Published records render using this site’s values.',
                    onChange: function (uuid) { props.setAttributes({uuid: uuid}); }
                })
            );
        },
        save: function () { return null; }
    });
})(window.wp);
