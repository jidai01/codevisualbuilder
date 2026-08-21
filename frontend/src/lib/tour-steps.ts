import { Step } from 'react-joyride';

export const tourSteps: Step[] = [
  {
    target: '[data-tour="node-palette"]',
    title: 'Welcome to the Canvas!',
    content: 'Start here by dragging a \'Table Node\' onto the canvas to create your first database model.',
    placement: 'right',
    skipBeacon: true,
  },
  {
    target: '[data-tour="canvas-area"]',
    title: 'Design Your Schema',
    content: 'Click on a node to add columns, define data types, and set constraints. Each node represents a database table.',
    placement: 'center',
    skipBeacon: true,
  },
  {
    target: '[data-tour="connection-handle"]',
    title: 'Build Relationships',
    content: 'Drag from one table\'s handle to another to instantly create 1:N or N:N relationships. The backend will automatically handle the foreign keys!',
    placement: 'top',
    skipBeacon: true,
  },
  {
    target: '[data-tour="generate-button"]',
    title: 'Generate Your Project',
    content: 'When your schema is ready, click here to compile your blueprint into a full Laravel 12 + Filament project.',
    placement: 'left',
    skipBeacon: true,
  },
  {
    target: '[data-tour="export-button"]',
    title: 'Export & Download',
    content: 'After generation, use the Export panel to initialize Git or download your project as a ZIP file.',
    placement: 'left',
    skipBeacon: true,
  },
];
