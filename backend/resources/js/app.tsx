import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import Foundation from './pages/foundation';

void createInertiaApp({
    title: (title) => `${title} | mobiST Tech`,
    resolve: (name) => {
        if (name !== 'foundation') throw new Error('Unknown foundation page');
        return Foundation;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
