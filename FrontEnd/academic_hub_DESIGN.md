---
name: Academic Hub
colors:
  surface: '#f8f9fb'
  surface-dim: '#d9dadc'
  surface-bright: '#f8f9fb'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f3f4f6'
  surface-container: '#edeef0'
  surface-container-high: '#e7e8ea'
  surface-container-highest: '#e1e2e4'
  on-surface: '#191c1e'
  on-surface-variant: '#554243'
  inverse-surface: '#2e3132'
  inverse-on-surface: '#f0f1f3'
  outline: '#887273'
  outline-variant: '#dbc0c1'
  surface-tint: '#9b404d'
  primary: '#5c101f'
  on-primary: '#ffffff'
  primary-container: '#7a2734'
  on-primary-container: '#ff929d'
  inverse-primary: '#ffb2b8'
  secondary: '#a53d00'
  on-secondary: '#ffffff'
  secondary-container: '#ff7e44'
  on-secondary-container: '#682300'
  tertiary: '#00361d'
  on-tertiary: '#ffffff'
  tertiary-container: '#004f2d'
  on-tertiary-container: '#7cc094'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#ffdadb'
  primary-fixed-dim: '#ffb2b8'
  on-primary-fixed: '#40000f'
  on-primary-fixed-variant: '#7d2936'
  secondary-fixed: '#ffdbcd'
  secondary-fixed-dim: '#ffb597'
  on-secondary-fixed: '#360f00'
  on-secondary-fixed-variant: '#7e2c00'
  tertiary-fixed: '#acf2c3'
  tertiary-fixed-dim: '#91d5a8'
  on-tertiary-fixed: '#002110'
  on-tertiary-fixed-variant: '#05522f'
  background: '#f8f9fb'
  on-background: '#191c1e'
  surface-variant: '#e1e2e4'
  status-success: '#4A7C3F'
  accent-stats: '#8B5FA3'
  surface-white: '#FFFFFF'
  text-primary: '#1F2328'
  text-secondary: '#6B7280'
typography:
  headline-xl:
    fontFamily: Noto Sans Thai
    fontSize: 48px
    fontWeight: '800'
    lineHeight: '1.2'
  headline-lg:
    fontFamily: Noto Sans Thai
    fontSize: 32px
    fontWeight: '700'
    lineHeight: '1.3'
  headline-md:
    fontFamily: Noto Sans Thai
    fontSize: 24px
    fontWeight: '700'
    lineHeight: '1.4'
  body-lg:
    fontFamily: Noto Sans Thai
    fontSize: 18px
    fontWeight: '400'
    lineHeight: '1.6'
  body-md:
    fontFamily: Noto Sans Thai
    fontSize: 16px
    fontWeight: '400'
    lineHeight: '1.5'
  label-code:
    fontFamily: IBM Plex Mono
    fontSize: 14px
    fontWeight: '500'
    lineHeight: '1.2'
  label-caps:
    fontFamily: IBM Plex Mono
    fontSize: 12px
    fontWeight: '600'
    lineHeight: '1.2'
    letterSpacing: 0.05em
  headline-xl-mobile:
    fontFamily: Noto Sans Thai
    fontSize: 32px
    fontWeight: '800'
    lineHeight: '1.2'
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  base: 8px
  margin-mobile: 16px
  margin-desktop: 48px
  gutter: 24px
  hero-height: 400px
---

## Brand & Style
The design system is engineered for the **Nakhon Nayok Technical College** library, prioritizing efficiency, authority, and accessibility. The brand personality is **Academic and Professional**, discarding the "dusty archive" aesthetic in favor of a modern, tech-forward institution. 

The design style follows a **Modern Corporate** approach with **Tactile/High-Contrast** elements. It emphasizes clarity through a high-contrast color palette and clear information hierarchy. To represent the library's physical nature, the UI incorporates subtle vertical patterns (inspired by book spines) and "stamp-like" UI components that provide a sense of official confirmation and physical action. The target audience includes students and faculty who require a fast, low-friction experience for managing academic resources.

## Colors
The color strategy utilizes a deep **Maroon** as the foundation of authority and institutional identity. This is paired with a high-energy **Burnt Orange** for Call-to-Action (CTA) elements, ensuring that "Check-in" or "Sign up" actions are never missed. 

**Color Usage Guidelines:**
- **Primary (Maroon):** Used for structural navigation, global headers, and primary branding.
- **Secondary (Burnt Orange):** Reserved exclusively for high-priority actions and transactional buttons.
- **Backgrounds:** A soft grey (#F5F6F8) reduces eye strain compared to pure white, while white (#FFFFFF) is reserved for card surfaces to create distinct layering.
- **Status & Accents:** Green is used for active states and successful transactions. Purple is used as a data-visualization color to distinguish statistical insights from functional UI.

## Typography
The system uses a dual-font approach. **Noto Sans Thai** serves as the primary typeface, chosen for its excellent readability in both Thai and Latin scripts and its modern, humanist character. **IBM Plex Mono** is used for technical data, book IDs, barcodes, and labels to evoke a sense of precision and systemic organization.

Headers use heavy weights (700-800) to establish a strong visual hierarchy. Body text is kept at a comfortable 16-18px range to ensure high accessibility for all students.

## Layout & Spacing
This design system employs a **Fixed Grid** system for desktop (max-width 1280px) and a **Fluid Grid** for mobile. 

- **Grid:** 12-column layout on desktop, 4-column on mobile.
- **Hero Section:** Features a dark maroon gradient with a 1px vertical line pattern. It uses a negative margin-bottom to allow dashboard cards to "float" over the transition between the hero and the main content area.
- **Rhythm:** An 8px base unit (1rem = 16px) governs all spacing. Vertical rhythm is strictly enforced in data tables to ensure readability.

## Elevation & Depth
Depth is created through **Tonal Layers** and **Soft Ambient Shadows**. 

1. **Base Layer:** Background (#F5F6F8) acts as the canvas.
2. **Surface Layer:** White cards (#FFFFFF) use a subtle shadow (0px 4px 20px rgba(0, 0, 0, 0.05)) to appear lifted.
3. **Interactive Layer:** Active buttons and "Stamp" components use a more pronounced shadow (0px 8px 24px rgba(122, 39, 52, 0.2)) to signify clickability.
4. **Hero Elements:** Use negative margins and layering to create a 3D effect of cards resting on top of the dark header background.

## Shapes
A **Rounded** geometry (8px / 0.5rem) is used for all standard UI elements including cards and input fields. This balances professionalism with modern approachability. 

Special exceptions are made for the **Check-in/Check-out Stamp**, which is a perfect circle. This circular shape disrupts the rectangular grid, drawing immediate attention to the primary functional purpose of the application.

## Components
- **The Stamp Button:** A large, circular action button for Check-in/Check-out. It must feature a "pulse" animation (a subtle expanding ring) to guide the user's eye and a tactile hover state that "presses" into the screen.
- **Hero Patterns:** Use 1px wide vertical lines of varying heights (#FFFFFF at 5% opacity) against the Maroon gradient background to subtly mimic book spines on a shelf.
- **Dashboard Widgets:** Compact cards showing "Books Due," "Recent Activity," and "Late Fees." Use **IBM Plex Mono** for all numerical values.
- **Data Tables:** High-contrast rows with `text-primary`. The header row should be a light tint of Maroon or Grey to distinguish from the body.
- **Quick Stats:** Icon rows using the `accent-stats` (Purple) for the icon containers to provide a visual break from the primary brand colors.
- **Inputs:** Large touch targets (min 48px height) with 1px borders in `text-secondary`, turning `primary` on focus.