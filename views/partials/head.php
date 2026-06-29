<?php require_once __DIR__ . '/flags.php'; ?>
<!DOCTYPE html>
<html class="dark" lang="<?= App\Src\Language::currentLang() ?>">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title><?= htmlspecialchars($pageTitle ?? 'AiTut') ?></title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&amp;family=Hanken+Grotesk:wght@600;700;800&amp;family=Geist:wght@400;500&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<style>
  .material-symbols-outlined {
    font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
  }
  .glass-panel {
    background: rgba(18, 33, 49, 0.7);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(67, 70, 85, 0.5);
  }
  .chat-scrollbar::-webkit-scrollbar { width: 4px; }
  .chat-scrollbar::-webkit-scrollbar-track { background: transparent; }
  .chat-scrollbar::-webkit-scrollbar-thumb { background: #3f465c; border-radius: 10px; }
  .glow-hover:hover { box-shadow: 0 0 15px rgba(180, 197, 255, 0.2); }
  .nav-link {
    font-family: 'Geist', sans-serif;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-size: 11px;
    font-weight: 500;
  }
  body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
  ::-webkit-scrollbar { width: 4px; }
  ::-webkit-scrollbar-track { background: transparent; }
  ::-webkit-scrollbar-thumb { background: #3f465c; border-radius: 10px; }
</style>
<script>
tailwind.config = {
  darkMode: "class",
  theme: {
    extend: {
      colors: {
        "on-secondary-fixed": "#131b2e",
        "primary": "#b4c5ff",
        "tertiary-fixed": "#c4e7ff",
        "on-tertiary-fixed": "#001e2c",
        "inverse-on-surface": "#233143",
        "secondary-container": "#3f465c",
        "on-surface": "#d4e4fa",
        "surface-dim": "#051424",
        "surface-container-highest": "#273647",
        "error-container": "#93000a",
        "on-primary": "#002a78",
        "background": "#051424",
        "on-primary-container": "#eeefff",
        "on-background": "#d4e4fa",
        "outline": "#8d90a0",
        "on-primary-fixed-variant": "#003ea8",
        "primary-fixed": "#dbe1ff",
        "on-tertiary-fixed-variant": "#004c69",
        "on-primary-fixed": "#00174b",
        "error": "#ffb4ab",
        "primary-fixed-dim": "#b4c5ff",
        "surface-bright": "#2c3a4c",
        "primary-container": "#2563eb",
        "on-error-container": "#ffdad6",
        "surface-container-lowest": "#010f1f",
        "surface-container-low": "#0d1c2d",
        "tertiary": "#7bd0ff",
        "secondary-fixed": "#dae2fd",
        "secondary-fixed-dim": "#bec6e0",
        "outline-variant": "#434655",
        "surface-container": "#122131",
        "on-surface-variant": "#c3c6d7",
        "on-error": "#690005",
        "surface-variant": "#273647",
        "on-secondary-container": "#adb4ce",
        "on-secondary": "#283044",
        "surface-tint": "#b4c5ff",
        "inverse-surface": "#d4e4fa",
        "on-tertiary-container": "#e1f2ff",
        "surface": "#051424",
        "on-tertiary": "#00354a",
        "surface-container-high": "#1c2b3c",
        "inverse-primary": "#0053db",
        "on-secondary-fixed-variant": "#3f465c",
        "tertiary-fixed-dim": "#7bd0ff",
        "secondary": "#bec6e0",
        "tertiary-container": "#00759f"
      },
      borderRadius: {
        DEFAULT: "0.25rem", lg: "0.5rem", xl: "0.75rem", full: "9999px"
      },
      spacing: {
        lg: "24px", xl: "32px", gutter: "20px",
        "margin-desktop": "32px", md: "16px",
        xs: "4px", sm: "8px", base: "4px", "margin-mobile": "16px"
      },
      fontFamily: {
        "body-lg": ["Inter"], "headline-sm": ["Hanken Grotesk"],
        "headline-lg": ["Hanken Grotesk"], "body-md": ["Inter"],
        "headline-lg-mobile": ["Hanken Grotesk"], "label-md": ["Geist"],
        "headline-md": ["Hanken Grotesk"]
      },
      fontSize: {
        "body-lg": ["16px", {lineHeight: "24px", fontWeight: "400"}],
        "headline-sm": ["20px", {lineHeight: "28px", fontWeight: "600"}],
        "headline-lg": ["32px", {lineHeight: "40px", letterSpacing: "-0.02em", fontWeight: "700"}],
        "body-md": ["14px", {lineHeight: "20px", fontWeight: "400"}],
        "headline-lg-mobile": ["26px", {lineHeight: "32px", fontWeight: "700"}],
        "label-md": ["12px", {lineHeight: "16px", letterSpacing: "0.05em", fontWeight: "500"}],
        "headline-md": ["24px", {lineHeight: "32px", letterSpacing: "-0.01em", fontWeight: "600"}]
      }
    }
  }
}
</script>
</head>
<body class="bg-surface-dim text-on-surface font-body-md overflow-hidden h-screen flex flex-col">
