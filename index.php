<html>
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>How to use Fee SPA Sync</title>

	<!-- Google Fonts -->
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

	<style>
		:root {
			--bg-gradient: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #311042 100%);
			--card-bg: rgba(255, 255, 255, 0.03);
			--card-border: rgba(255, 255, 255, 0.08);
			--text-primary: #f8fafc;
			--text-secondary: #94a3b8;
			--accent-blue: #3b82f6;
			--accent-purple: #a855f7;
			--accent-emerald: #10b981;
			--accent-gradient: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 50%, #d946ef 100%);
		}

		* {
			box-sizing: border-box;
			margin: 0;
			padding: 0;
		}

		body {
			font-family: 'Inter', sans-serif;
			background: var(--bg-gradient);
			color: var(--text-primary);
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 2rem 1rem;
			overflow-x: hidden;
		}

		.container {
			max-width: 1100px;
			width: 100%;
			margin: 0 auto;
		}

		/* Header Section */
		.header {
			text-align: center;
			margin-bottom: 3.5rem;
			position: relative;
		}

		.app-badge {
			display: inline-flex;
			align-items: center;
			gap: 0.5rem;
			background: rgba(16, 185, 129, 0.1);
			border: 1px solid rgba(16, 185, 129, 0.2);
			color: var(--accent-emerald);
			padding: 0.35rem 0.85rem;
			border-radius: 9999px;
			font-size: 0.75rem;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: 0.05em;
			margin-bottom: 1rem;
		}

		.pulse-dot {
			width: 6px;
			height: 6px;
			background: var(--accent-emerald);
			border-radius: 50%;
			box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
			animation: pulse 1.5s infinite;
		}

		@keyframes pulse {
			0% {
				transform: scale(0.95);
				box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
			}
			70% {
				transform: scale(1);
				box-shadow: 0 0 0 6px rgba(16, 185, 129, 0);
			}
			100% {
				transform: scale(0.95);
				box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
			}
		}

		.header h1 {
			font-family: 'Plus Jakarta Sans', sans-serif;
			font-size: 2.75rem;
			font-weight: 700;
			line-height: 1.25;
			background: var(--accent-gradient);
			-webkit-background-clip: text;
			-webkit-text-fill-color: transparent;
			margin-bottom: 0.75rem;
		}

		.header p {
			color: var(--text-secondary);
			font-size: 1.1rem;
			max-width: 600px;
			margin: 0 auto;
		}

		/* Steps Grid */
		.steps-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
			gap: 1.75rem;
			margin-bottom: 3.5rem;
		}

		.step-card {
			background: var(--card-bg);
			border: 1px solid var(--card-border);
			border-radius: 16px;
			padding: 2.25rem 2rem;
			position: relative;
			transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
			backdrop-filter: blur(10px);
			overflow: hidden;
		}

		.step-card::before {
			content: '';
			position: absolute;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: linear-gradient(180deg, rgba(255, 255, 255, 0.03) 0%, transparent 100%);
			opacity: 0;
			transition: opacity 0.3s ease;
		}

		.step-card:hover {
			transform: translateY(-5px);
			border-color: rgba(255, 255, 255, 0.15);
			box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.5);
		}

		.step-card:hover::before {
			opacity: 1;
		}

		.step-icon-wrapper {
			width: 50px;
			height: 50px;
			border-radius: 12px;
			display: flex;
			align-items: center;
			justify-content: center;
			margin-bottom: 1.5rem;
			background: rgba(255, 255, 255, 0.05);
			border: 1px solid rgba(255, 255, 255, 0.08);
			color: var(--accent-blue);
		}

		.step-card:nth-child(2) .step-icon-wrapper {
			color: var(--accent-purple);
		}

		.step-card:nth-child(3) .step-icon-wrapper {
			color: var(--accent-emerald);
		}

		.step-number {
			position: absolute;
			top: 2rem;
			right: 2rem;
			font-family: 'Plus Jakarta Sans', sans-serif;
			font-size: 3.5rem;
			font-weight: 800;
			line-height: 1;
			color: rgba(255, 255, 255, 0.03);
			user-select: none;
			transition: color 0.3s ease;
		}

		.step-card:hover .step-number {
			color: rgba(255, 255, 255, 0.06);
		}

		.step-title {
			font-family: 'Plus Jakarta Sans', sans-serif;
			font-size: 1.25rem;
			font-weight: 600;
			margin-bottom: 0.75rem;
			color: var(--text-primary);
		}

		.step-desc {
			color: var(--text-secondary);
			font-size: 0.95rem;
			line-height: 1.6;
		}

		/* Status Bar */
		.status-bar {
			background: rgba(255, 255, 255, 0.02);
			border: 1px solid var(--card-border);
			border-radius: 12px;
			padding: 1.25rem 1.5rem;
			display: flex;
			align-items: center;
			justify-content: space-between;
			flex-wrap: wrap;
			gap: 1rem;
		}

		.status-item {
			display: flex;
			align-items: center;
			gap: 0.5rem;
			font-size: 0.85rem;
			color: var(--text-secondary);
		}

		.status-item strong {
			color: var(--text-primary);
		}

		.status-dot {
			width: 8px;
			height: 8px;
			border-radius: 50%;
			background: var(--accent-emerald);
		}

		@media (max-width: 768px) {
			.header h1 {
				font-size: 2.25rem;
			}
			.steps-grid {
				grid-template-columns: 1fr;
			}
		}
	</style>
</head>
<body>
	<div class="container">
		<div class="header">
			<div class="app-badge">
				<div class="pulse-dot"></div>
				System Connected
			</div>
			<h1>Fee SPA Sync Guide</h1>
			<p>Automate and manage synchronization between Lead / Deal products and Single Page Application (SPA) fee records seamlessly.</p>
		</div>

		<div class="steps-grid">
			<!-- Step 1 -->
			<div class="step-card">
				<div class="step-number">01</div>
				<div class="step-icon-wrapper">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
				</div>
				<h3 class="step-title">Assign Cost Types</h3>
				<p class="step-desc">Add products to your Deals or Leads and set their <strong>Type of Cost</strong> property (e.g. <em>Government Cost</em> or <em>Professional Cost</em>).</p>
			</div>

			<!-- Step 2 -->
			<div class="step-card">
				<div class="step-number">02</div>
				<div class="step-icon-wrapper">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
				</div>
				<h3 class="step-title">Real-Time Syncing</h3>
				<p class="step-desc">The widget monitors modifications instantly. Any change to products will auto-sync to <strong>Professional Fees</strong> (SPA 1058) or <strong>Government Fees</strong> (SPA 1062) SPAs.</p>
			</div>

			<!-- Step 3 -->
			<div class="step-card">
				<div class="step-number">03</div>
				<div class="step-icon-wrapper">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 16 4 4 4-4M7 20V4M21 8l-4-4-4 4M17 4v16"/></svg>
				</div>
				<h3 class="step-title">Bidirectional Updates</h3>
				<p class="step-desc">Modify SPA item properties like price or quantity directly. Changes sync back to the source Deal/Lead product row automatically.</p>
			</div>
		</div>

		<div class="status-bar">
			<div class="status-item">
				<div class="status-dot"></div>
				Status: <strong>Active</strong>
			</div>
			<div class="status-item">
				Active Placements: <strong>CRM_DEAL_DETAIL_TAB, CRM_LEAD_DETAIL_TAB</strong>
			</div>
			<div class="status-item">
				Version: <strong>1.2.0 (Real-Time)</strong>
			</div>
		</div>
	</div>
</body>
</html>