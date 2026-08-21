'use client';

import { useState, useEffect } from 'react';

interface ExportPanelProps {
  uuid: string;
  projectName: string;
}

interface GitStatus {
  initialized: boolean;
  commits?: { hash: string; message: string }[];
}

export default function ExportPanel({ uuid, projectName }: ExportPanelProps) {
  const [gitStatus, setGitStatus] = useState<GitStatus | null>(null);
  const [gitLoading, setGitLoading] = useState(false);
  const [gitMessage, setGitMessage] = useState<string | null>(null);
  const [gitError, setGitError] = useState<string | null>(null);
  const [downloading, setDownloading] = useState(false);

  useEffect(() => {
    checkGitStatus();
  }, [uuid]);

  const checkGitStatus = async () => {
    try {
      const res = await fetch(`http://localhost:8000/api/workspace/${uuid}/git/status`);
      const data = await res.json();
      setGitStatus(data);
    } catch (err) {
      console.error('Failed to check git status:', err);
    }
  };

  const handleGitInit = async () => {
    setGitLoading(true);
    setGitMessage(null);
    setGitError(null);

    try {
      const res = await fetch(`http://localhost:8000/api/workspace/${uuid}/git/init`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          user_name: 'Builder Bot',
          user_email: 'bot@local.builder',
        }),
      });

      const data = await res.json();

      if (data.success) {
        setGitMessage(data.message);
        checkGitStatus();
      } else {
        setGitError(data.error || 'Failed to initialize Git');
      }
    } catch (err) {
      setGitError('Failed to connect to backend');
    } finally {
      setGitLoading(false);
    }
  };

  const handleDownload = async () => {
    setDownloading(true);

    try {
      const link = document.createElement('a');
      link.href = `http://localhost:8000/api/workspace/${uuid}/download`;
      link.download = `${projectName}.zip`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    } catch (err) {
      console.error('Download failed:', err);
    } finally {
      setTimeout(() => setDownloading(false), 2000);
    }
  };

  return (
    <div style={{
      background: '#252526',
      borderRadius: '8px',
      border: '1px solid #444',
      padding: '16px',
      display: 'flex',
      flexDirection: 'column',
      gap: '12px',
    }}>
      <div style={{ fontSize: '12px', color: '#94a3b8', textTransform: 'uppercase', fontWeight: 600, letterSpacing: '0.5px' }}>
        Export &amp; Distribution
      </div>

      {/* Git Section */}
      <div style={{
        background: '#1e1e1e',
        borderRadius: '6px',
        padding: '12px',
        border: '1px solid #333',
      }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '8px' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
            <span style={{ fontSize: '14px' }}>📁</span>
            <span style={{ color: '#e5e5e5', fontWeight: 500, fontSize: '13px' }}>Local Git</span>
          </div>
          {gitStatus?.initialized && (
            <span style={{
              fontSize: '11px',
              color: '#4ade80',
              background: '#4ade8022',
              padding: '2px 8px',
              borderRadius: '12px',
            }}>
              ✓ Initialized
            </span>
          )}
        </div>

        {gitStatus?.initialized && gitStatus.commits && gitStatus.commits.length > 0 && (
          <div style={{ marginBottom: '8px' }}>
            {gitStatus.commits.slice(0, 3).map((commit, i) => (
              <div key={i} style={{ fontSize: '11px', color: '#888', fontFamily: 'monospace', marginBottom: '2px' }}>
                <span style={{ color: '#fbbf24' }}>{commit.hash.slice(0, 7)}</span>
                <span style={{ color: '#aaa', marginLeft: '6px' }}>{commit.message}</span>
              </div>
            ))}
          </div>
        )}

        <button
          onClick={handleGitInit}
          disabled={gitLoading}
          style={{
            width: '100%',
            padding: '8px 12px',
            background: gitStatus?.initialized ? '#333' : '#8b5cf6',
            color: 'white',
            border: 'none',
            borderRadius: '6px',
            cursor: gitLoading ? 'wait' : 'pointer',
            fontWeight: 600,
            fontSize: '13px',
            opacity: gitLoading ? 0.7 : 1,
          }}
        >
          {gitLoading ? 'Initializing...' : gitStatus?.initialized ? 'Re-initialize Git' : 'Commit to Local Git'}
        </button>

        {gitMessage && (
          <div style={{ marginTop: '8px', fontSize: '12px', color: '#4ade80' }}>{gitMessage}</div>
        )}
        {gitError && (
          <div style={{ marginTop: '8px', fontSize: '12px', color: '#f87171' }}>{gitError}</div>
        )}
      </div>

      {/* ZIP Section */}
      <div style={{
        background: '#1e1e1e',
        borderRadius: '6px',
        padding: '12px',
        border: '1px solid #333',
      }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '8px' }}>
          <span style={{ fontSize: '14px' }}>📦</span>
          <span style={{ color: '#e5e5e5', fontWeight: 500, fontSize: '13px' }}>ZIP Archive</span>
        </div>

        <div style={{ fontSize: '12px', color: '#888', marginBottom: '8px' }}>
          Download the complete project as a ZIP file
        </div>

        <button
          onClick={handleDownload}
          disabled={downloading}
          style={{
            width: '100%',
            padding: '8px 12px',
            background: '#3b82f6',
            color: 'white',
            border: 'none',
            borderRadius: '6px',
            cursor: downloading ? 'wait' : 'pointer',
            fontWeight: 600,
            fontSize: '13px',
            opacity: downloading ? 0.7 : 1,
          }}
        >
          {downloading ? 'Preparing...' : `Download ${projectName}.zip`}
        </button>
      </div>
    </div>
  );
}
