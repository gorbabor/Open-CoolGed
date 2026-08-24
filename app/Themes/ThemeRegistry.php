<?php

namespace App\Themes;

use App\Models\PlatformSettings;

/**
 * Catalogue de thèmes visuels. Chaque thème définit une palette claire ET sombre
 * (background/surface/foreground/accent/muted), les polices (Google Fonts) et le radius.
 * Résolution : utilisateur (users.theme) > tenant (branding.theme) > plateforme > ocean.
 * Mode : préférence utilisateur (users.theme_mode) > mode tenant > mode plateforme > auto.
 */
class ThemeRegistry
{
    /** Thème appliqué par défaut (aucun choix explicite). */
    public const DEFAULT_THEME = 'ocean';

    public const THEMES = [
        'kami' => [
            'label' => 'Kami',
            'font' => 'ui-monospace, Cascadia Code, monospace',
            'fontLink' => null,
            'radius' => '8px',
            'palettes' => [
                'light' => ['background' => '#f8fafc', 'surface' => '#f4dcdc', 'foreground' => '#0f172a', 'accent' => '#4f46e5', 'muted' => '#64748b'],
                'dark' => ['background' => '#0f172a', 'surface' => '#1e293b', 'foreground' => '#f8fafc', 'accent' => '#818cf8', 'muted' => '#94a3b8'],
            ],
        ],
        'ocean' => [
            'label' => 'Ocean',
            'font' => "'Inter', sans-serif",
            'fontLink' => 'family=Inter&family=Merriweather',
            'radius' => '10px',
            'palettes' => [
                'light' => ['background' => '#f0f7fb', 'surface' => '#dcebf5', 'foreground' => '#0c2d48', 'accent' => '#1d7ab3', 'muted' => '#5b7c99'],
                'dark' => ['background' => '#0b1c2a', 'surface' => '#14303f', 'foreground' => '#e8f4fb', 'accent' => '#4aa3df', 'muted' => '#7da3bd'],
            ],
        ],
        'forest' => [
            'label' => 'Forest',
            'font' => "'Lora', serif",
            'fontLink' => 'family=Lora',
            'radius' => '6px',
            'palettes' => [
                'light' => ['background' => '#f2f7f2', 'surface' => '#ddeedd', 'foreground' => '#14281a', 'accent' => '#2f7d4f', 'muted' => '#5f7f6a'],
                'dark' => ['background' => '#0f1d12', 'surface' => '#1c3322', 'foreground' => '#eaf5ec', 'accent' => '#4fa06b', 'muted' => '#7fa68c'],
            ],
        ],
        'sunset' => [
            'label' => 'Sunset',
            'font' => "'Karla', sans-serif",
            'fontLink' => 'family=Karla&family=Fraunces',
            'radius' => '12px',
            'palettes' => [
                'light' => ['background' => '#fdf6f0', 'surface' => '#f7e3d3', 'foreground' => '#3d1f10', 'accent' => '#d97742', 'muted' => '#8a6a55'],
                'dark' => ['background' => '#241109', 'surface' => '#3b2012', 'foreground' => '#fdf0e6', 'accent' => '#e8955f', 'muted' => '#b18a70'],
            ],
        ],
        'mono' => [
            'label' => 'Mono',
            'font' => "'Inter', sans-serif",
            'fontLink' => 'family=Inter',
            'radius' => '4px',
            'palettes' => [
                'light' => ['background' => '#fafafa', 'surface' => '#ececec', 'foreground' => '#111111', 'accent' => '#333333', 'muted' => '#6b6b6b'],
                'dark' => ['background' => '#111111', 'surface' => '#1f1f1f', 'foreground' => '#f5f5f5', 'accent' => '#888888', 'muted' => '#9a9a9a'],
            ],
        ],
        'lavande' => [
            'label' => 'Lavande',
            'font' => "'Nunito', sans-serif",
            'fontLink' => 'family=Nunito&family=Playfair+Display',
            'radius' => '10px',
            'palettes' => [
                'light' => ['background' => '#faf8ff', 'surface' => '#ece7f8', 'foreground' => '#1c1440', 'accent' => '#7c5cd6', 'muted' => '#6f6593'],
                'dark' => ['background' => '#151029', 'surface' => '#251d45', 'foreground' => '#f1edfc', 'accent' => '#9a7ff0', 'muted' => '#8f86b3'],
            ],
        ],
        'slate' => [
            'label' => 'Slate',
            'font' => "'IBM Plex Sans', sans-serif",
            'fontLink' => 'family=IBM+Plex+Sans&family=IBM+Plex+Serif',
            'radius' => '6px',
            'palettes' => [
                'light' => ['background' => '#f8fafc', 'surface' => '#e2e8f0', 'foreground' => '#1e293b', 'accent' => '#475569', 'muted' => '#64748b'],
                'dark' => ['background' => '#0f172a', 'surface' => '#1e293b', 'foreground' => '#f1f5f9', 'accent' => '#94a3b8', 'muted' => '#64748b'],
            ],
        ],
        'rose' => [
            'label' => 'Rose',
            'font' => "'Poppins', sans-serif",
            'fontLink' => 'family=Poppins&family=Playfair+Display',
            'radius' => '12px',
            'palettes' => [
                'light' => ['background' => '#fff7f7', 'surface' => '#fbe3e4', 'foreground' => '#3b1215', 'accent' => '#d64550', 'muted' => '#9c6f73'],
                'dark' => ['background' => '#230b0d', 'surface' => '#3a1417', 'foreground' => '#fdf0f0', 'accent' => '#ef6b76', 'muted' => '#b08a8d'],
            ],
        ],
        'mint' => [
            'label' => 'Mint',
            'font' => "'Manrope', sans-serif",
            'fontLink' => 'family=Manrope&family=Georgia',
            'radius' => '10px',
            'palettes' => [
                'light' => ['background' => '#f2fbf7', 'surface' => '#ddf3ea', 'foreground' => '#0e2f24', 'accent' => '#17a06d', 'muted' => '#5c8574'],
                'dark' => ['background' => '#081f17', 'surface' => '#123329', 'foreground' => '#e6faf2', 'accent' => '#34c08a', 'muted' => '#7fb3a0'],
            ],
        ],
        'amber' => [
            'label' => 'Amber',
            'font' => "'Rubik', sans-serif",
            'fontLink' => 'family=Rubik&family=Lora',
            'radius' => '8px',
            'palettes' => [
                'light' => ['background' => '#fffaf0', 'surface' => '#fdeeda', 'foreground' => '#3a2a08', 'accent' => '#c77d1d', 'muted' => '#8d7a55'],
                'dark' => ['background' => '#241a06', 'surface' => '#3c2e0e', 'foreground' => '#fdf3dd', 'accent' => '#e09a3e', 'muted' => '#b39a66'],
            ],
        ],
        'sky' => [
            'label' => 'Sky',
            'font' => "'Outfit', sans-serif",
            'fontLink' => 'family=Outfit&family=Lora',
            'radius' => '10px',
            'palettes' => [
                'light' => ['background' => '#f5fbff', 'surface' => '#dceefb', 'foreground' => '#07283f', 'accent' => '#0e7fc4', 'muted' => '#5f86a0'],
                'dark' => ['background' => '#071c2c', 'surface' => '#0f2c40', 'foreground' => '#e6f4fd', 'accent' => '#3aa1e6', 'muted' => '#7da3bd'],
            ],
        ],
        'ink' => [
            'label' => 'Ink',
            'font' => "'Source Sans 3', sans-serif",
            'fontLink' => 'family=Source+Sans+3&family=Fraunces',
            'radius' => '6px',
            'palettes' => [
                'light' => ['background' => '#fdfdfb', 'surface' => '#f0efe9', 'foreground' => '#1a1a18', 'accent' => '#b45309', 'muted' => '#6f6e66'],
                'dark' => ['background' => '#171714', 'surface' => '#262620', 'foreground' => '#f5f4ef', 'accent' => '#d97706', 'muted' => '#8f8e84'],
            ],
        ],
    ];

    public function all(): array
    {
        return self::THEMES;
    }

    public function get(string $slug): array
    {
        return self::THEMES[$slug] ?? self::THEMES[self::DEFAULT_THEME];
    }

    /** Thème résolu : utilisateur (users.theme) > tenant (branding.theme) > plateforme > ocean. */
    public function resolveTheme(): string
    {
        $user = auth()->user();

        if ($user?->theme && isset(self::THEMES[$user->theme])) {
            return $user->theme;
        }

        if ($user?->tenant && ! empty($user->tenant->branding['theme'])) {
            $slug = $user->tenant->branding['theme'];
            if (isset(self::THEMES[$slug])) {
                return $slug;
            }
        }

        $platform = PlatformSettings::instance()->get('theme', null);
        if ($platform !== null && isset(self::THEMES[$platform])) {
            return $platform;
        }

        return self::DEFAULT_THEME;
    }

    /** Mode résolu : utilisateur (theme_mode) > tenant > plateforme > auto. */
    public function resolveMode(): string
    {
        $user = auth()->user();

        if ($user?->theme_mode && in_array($user->theme_mode, ['light', 'dark', 'auto'], true)) {
            return $user->theme_mode;
        }

        if ($user?->tenant && in_array($user->tenant->branding['theme_mode'] ?? null, ['light', 'dark', 'auto'], true)) {
            return $user->tenant->branding['theme_mode'];
        }

        $platform = PlatformSettings::instance()->get('theme_mode', null);
        if (in_array($platform, ['light', 'dark', 'auto'], true)) {
            return $platform;
        }

        return 'auto';
    }

    /** Palette effective (clair ou sombre) pour le contexte courant. */
    public function palette(string $mode = 'light'): array
    {
        $theme = $this->get($this->resolveTheme());

        return $theme['palettes'][$mode] ?? $theme['palettes']['light'];
    }
}
