window.docsearch = require('docsearch.js');

import hljs from 'highlight.js/lib/core';

hljs.registerLanguage('bash', require('highlight.js/lib/languages/bash'));
hljs.registerLanguage('css', require('highlight.js/lib/languages/css'));
hljs.registerLanguage('html', require('highlight.js/lib/languages/xml'));
hljs.registerLanguage('javascript', require('highlight.js/lib/languages/javascript'));
hljs.registerLanguage('json', require('highlight.js/lib/languages/json'));
hljs.registerLanguage('markdown', require('highlight.js/lib/languages/markdown'));
hljs.registerLanguage('php', require('highlight.js/lib/languages/php'));
hljs.registerLanguage('scss', require('highlight.js/lib/languages/scss'));
hljs.registerLanguage('yaml', require('highlight.js/lib/languages/yaml'));
hljs.registerLanguage('python', require('highlight.js/lib/languages/python'));
hljs.registerLanguage('sql', require('highlight.js/lib/languages/sql'));

hljs.registerLanguage('turtle', require('./highlightjs/languages/turtle'));



document.querySelectorAll('pre code').forEach((block) => {
    hljs.highlightBlock(block);
});

// Make external links open in new tab
document.querySelectorAll('.docsearch-content a[href^=http]').forEach((link) => {
    link.setAttribute('target', '_blank');
});


// Move able of contents to left navigation
const toc = document.querySelector('.docsearch-content .table-of-contents');
toc.classList.remove('table-of-contents');

const rightNav = document.createElement('nav');


const tocContainer = document.createElement('div');
tocContainer.id = 'table-of-contents';
tocContainer.classList.add('toc');

// const tocHeader = document.createElement('div');
// tocHeader.innerHTML = 'Table of contents';
// tocContainer.appendChild(tocHeader);

tocContainer.appendChild(toc);
rightNav.appendChild(tocContainer);

const content = document.querySelector('.docsearch-content');
content.after(rightNav);
