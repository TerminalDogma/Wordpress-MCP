/* Keep Gutenberg writes local and carry a compare-and-swap token. */
(function (wp) {
    'use strict';
    var config = window.mscEditor || {types: []};
    if (config.shared && wp.hooks) {
        wp.hooks.addFilter('editor.PostFeaturedImage', 'msc/owner-media', function () {
            return function () { return wp.element.createElement('p', {}, 'Choose the shared or local featured image under Shared Content → Manage fields.'); };
        });
    }
    wp.apiFetch.use(function (options, next) {
        var path = options.path || options.url || '';
        var shared = config.types.some(function (type) { return path.indexOf('/wp/v2/' + type + '/') !== -1; });
        var autosave = /\/autosaves(\?|$)/.test(path);
        if (shared && !autosave && options.method && options.method.toUpperCase() !== 'GET' && options.data && config.version !== null) {
            options = Object.assign({}, options, {data: Object.assign({}, options.data, {msc_expected_version: config.version})});
        }
        return next(options).then(function (result) {
            if (shared && result && result.msc_state) { config.version = result.msc_state.local_version; }
            return result;
        });
    });
})(window.wp);
