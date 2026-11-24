const fs = require('fs');
for (const [source, target] of [['node_modules/chart.js/dist/chart.umd.js','chart.js'],['node_modules/sweetalert2/dist/sweetalert2.all.min.js','sweetalert2.js']]) fs.copyFileSync(source, 'public/assets/'+target);
fs.copyFileSync('resources/js/app.js','public/assets/app.js');

fs.copyFileSync('resources/js/tenant-registration.js','public/assets/tenant-registration.js');
fs.copyFileSync('resources/js/rental-overrides.js','public/assets/rental-overrides.js');
