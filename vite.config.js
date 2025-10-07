import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import path from 'path';
import fs from 'fs';

function getFiles(dir, extension) {
    let files = [];
    fs.readdirSync(dir).forEach(file => {
        const fullPath = path.join(dir, file);
        if (fs.statSync(fullPath).isDirectory()) {
            files = files.concat(getFiles(fullPath, extension));
        } else if (fullPath.endsWith(extension)) {
            files.push(fullPath);
        }
    });
    return files;
}

export default defineConfig({
    plugins: [
        laravel({
            // input: ['resources/css/app.css', 'resources/js/app.js'],
            input: [
                ...getFiles('resources/assets/sass', '.scss'), // 獲取所有 SASS 檔案
                ...getFiles('resources/assets/js', '.js'),    // 獲取所有 JavaScript 檔案
            ],
            refresh: true,
        }),
    ],
});
