'use client';

import { useCallback, useEffect } from 'react';
import { Joyride, EventHandler, EventData, EVENTS, STATUS, Controls } from 'react-joyride';
import { useTourStore } from '@/lib/tour-store';
import { tourSteps } from '@/lib/tour-steps';

const joyrideStyles = {
  options: {
    primaryColor: '#3b82f6',
    backgroundColor: '#1e293b',
    textColor: '#e2e8f0',
    arrowColor: '#1e293b',
    overlayColor: 'rgba(0, 0, 0, 0.6)',
    zIndex: 10000,
  },
  buttonNext: {
    backgroundColor: '#3b82f6',
    color: '#ffffff',
    borderRadius: '6px',
    fontSize: '14px',
    fontWeight: '600',
    padding: '8px 16px',
    border: 'none',
    cursor: 'pointer',
  },
  buttonBack: {
    backgroundColor: 'transparent',
    color: '#94a3b8',
    borderRadius: '6px',
    fontSize: '14px',
    fontWeight: '500',
    padding: '8px 16px',
    border: '1px solid #475569',
    cursor: 'pointer',
  },
  buttonSkip: {
    backgroundColor: 'transparent',
    color: '#64748b',
    fontSize: '13px',
    padding: '8px 12px',
    border: 'none',
    cursor: 'pointer',
  },
  tooltip: {
    backgroundColor: '#1e293b',
    borderRadius: '12px',
    padding: '16px',
    boxShadow: '0 20px 60px rgba(0, 0, 0, 0.5)',
    border: '1px solid #334155',
    maxWidth: '360px',
  },
  tooltipContainer: {
    textAlign: 'left' as const,
  },
  tooltipTitle: {
    color: '#f1f5f9',
    fontSize: '16px',
    fontWeight: '700',
    marginBottom: '8px',
  },
  tooltipContent: {
    color: '#94a3b8',
    fontSize: '14px',
    lineHeight: '1.6',
  },
  tooltipFooter: {
    marginTop: '12px',
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
};

const joyrideLocale = {
  back: 'Back',
  close: 'Close',
  last: 'Finish',
  next: 'Next',
  open: 'Open the dialog',
  skip: 'Skip tour',
};

interface WalkthroughProps {
  onStepChange?: (index: number) => void;
}

export default function Walkthrough({ onStepChange }: WalkthroughProps) {
  const { run, tourComplete, stepIndex, stopTour, completeTour, setStepIndex } = useTourStore();

  const handleJoyrideCallback = useCallback(
    (data: EventData, controls: Controls) => {
      const { type, index, status } = data;

      if (type === EVENTS.STEP_AFTER) {
        setStepIndex((index || 0) + 1);
        onStepChange?.((index || 0) + 1);
      }

      if (type === EVENTS.TOUR_END || status === STATUS.FINISHED || status === STATUS.SKIPPED) {
        completeTour();
        stopTour();
      }
    },
    [setStepIndex, completeTour, stopTour, onStepChange]
  );

  return (
    <>
      <Joyride
        run={run}
        steps={tourSteps}
        stepIndex={stepIndex}
        onEvent={handleJoyrideCallback}
        styles={joyrideStyles}
        locale={joyrideLocale}
        options={{
          showProgress: true,
          spotlightRadius: 8,
          overlayColor: 'rgba(0, 0, 0, 0.6)',
        }}
      />

      <style jsx global>{`
        .react-joyride__tooltip {
          font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
        }

        .react-joyride__tooltip button[data-test-id="button-button"] {
          transition: all 0.2s ease !important;
        }

        .react-joyride__tooltip button[data-test-id="button-button"]:hover {
          transform: translateY(-1px) !important;
          box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4) !important;
        }

        .react-joyride__tooltip [data-test-id="button-back"]:hover {
          background-color: #334155 !important;
          color: #e2e8f0 !important;
        }

        .react-joyride__tooltip [data-test-id="button-skip"]:hover {
          color: #94a3b8 !important;
        }

        .react-joyride__spotlight {
          box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.3) !important;
        }

        [data-tour-highlighted] {
          position: relative;
          z-index: 9999;
        }
      `}</style>
    </>
  );
}

export function TourTrigger() {
  const { tourComplete, startTour, resetTour } = useTourStore();

  if (tourComplete) {
    return (
      <button
        onClick={resetTour}
        style={{
          position: 'fixed',
          bottom: '20px',
          right: '20px',
          width: '44px',
          height: '44px',
          borderRadius: '50%',
          backgroundColor: '#334155',
          color: '#94a3b8',
          border: '1px solid #475569',
          cursor: 'pointer',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          fontSize: '18px',
          zIndex: 9998,
          transition: 'all 0.2s',
          boxShadow: '0 4px 12px rgba(0,0,0,0.3)',
        }}
        onMouseEnter={(e) => {
          e.currentTarget.style.backgroundColor = '#3b82f6';
          e.currentTarget.style.color = '#ffffff';
          e.currentTarget.style.borderColor = '#3b82f6';
        }}
        onMouseLeave={(e) => {
          e.currentTarget.style.backgroundColor = '#334155';
          e.currentTarget.style.color = '#94a3b8';
          e.currentTarget.style.borderColor = '#475569';
        }}
        title="Restart tour"
      >
        ?
      </button>
    );
  }

  return (
    <button
      onClick={startTour}
      style={{
        position: 'fixed',
        bottom: '20px',
        right: '20px',
        padding: '10px 16px',
        borderRadius: '8px',
        backgroundColor: '#3b82f6',
        color: '#ffffff',
        border: 'none',
        cursor: 'pointer',
        fontSize: '13px',
        fontWeight: '600',
        zIndex: 9998,
        display: 'flex',
        alignItems: 'center',
        gap: '6px',
        boxShadow: '0 4px 12px rgba(59, 130, 246, 0.4)',
        transition: 'all 0.2s',
      }}
      onMouseEnter={(e) => {
        e.currentTarget.style.backgroundColor = '#2563eb';
        e.currentTarget.style.transform = 'translateY(-2px)';
      }}
      onMouseLeave={(e) => {
        e.currentTarget.style.backgroundColor = '#3b82f6';
        e.currentTarget.style.transform = 'translateY(0)';
      }}
    >
      <span style={{ fontSize: '16px' }}>?</span>
      Take a Tour
    </button>
  );
}
