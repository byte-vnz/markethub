---
name: MarketHub
description: A readable desktop and tablet workspace for LGU market operations.
colors:
  navy: "#11253f"
  brand: "#1d67d7"
  brand-hover: "#1e40af"
  navigation-active: "#24466c"
  canvas: "#f3f6fb"
  surface: "#ffffff"
  border: "#e2e8f0"
  field-border: "#cbd5e1"
  ink: "#1e293b"
  muted: "#475569"
  subtle: "#f8fafc"
  blue-soft: "#eff6ff"
  blue-ink: "#1e3a8a"
  occupied-soft: "#ecfdf5"
  occupied-ink: "#065f46"
  vacant-soft: "#f1f5f9"
  vacant-ink: "#334155"
  reserved-soft: "#fffbeb"
  reserved-ink: "#78350f"
  maintenance-soft: "#fef2f2"
  maintenance-ink: "#991b1b"
typography:
  headline:
    fontFamily: '"Segoe UI", Arial, sans-serif'
    fontSize: "24px"
    fontWeight: 700
    lineHeight: "32px"
    letterSpacing: "-0.025em"
  title:
    fontFamily: '"Segoe UI", Arial, sans-serif'
    fontSize: "18px"
    fontWeight: 600
    lineHeight: "28px"
  body:
    fontFamily: '"Segoe UI", Arial, sans-serif'
    fontSize: "14px"
    fontWeight: 400
    lineHeight: "20px"
  label:
    fontFamily: '"Segoe UI", Arial, sans-serif'
    fontSize: "14px"
    fontWeight: 500
    lineHeight: "20px"
  caption:
    fontFamily: '"Segoe UI", Arial, sans-serif'
    fontSize: "12px"
    lineHeight: "16px"
rounded:
  control: "8px"
  panel: "12px"
  badge: "9999px"
spacing:
  tight: "8px"
  compact: "12px"
  standard: "16px"
  panel: "20px"
  spacious: "24px"
  page: "32px"
components:
  button-primary:
    backgroundColor: "{colors.brand}"
    textColor: "{colors.surface}"
    rounded: "{rounded.control}"
    padding: "10px 16px"
  button-primary-hover:
    backgroundColor: "{colors.brand-hover}"
  button-secondary:
    backgroundColor: "{colors.blue-soft}"
    textColor: "{colors.blue-ink}"
    rounded: "{rounded.control}"
    padding: "10px 16px"
  button-danger:
    backgroundColor: "{colors.maintenance-soft}"
    textColor: "{colors.maintenance-ink}"
    rounded: "{rounded.control}"
    padding: "10px 16px"
  input:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.control}"
    padding: "10px 12px"
  navigation:
    backgroundColor: "{colors.navy}"
    padding: "10px 12px"
    rounded: "{rounded.control}"
  badge:
    backgroundColor: "{colors.vacant-soft}"
    textColor: "{colors.vacant-ink}"
    rounded: "{rounded.badge}"
    padding: "4px 10px"
  panel:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.panel}"
    padding: "20px"
  stall:
    rounded: "{rounded.control}"
    padding: "12px"
---

# Design System: MarketHub

## Overview

**Creative North Star: "The Market Operations Desk"**

MarketHub keeps the visual identity of the supplied prototype: a navy navigation rail, blue actions, a light work surface and white bordered panels. The interface supports repeat visits by LGU staff working with stalls, tenants and financial records on desktop and tablet. Its character is practical, compact and readable.

System typography, clear labels and familiar controls carry the work. The market grid is the signature visual surface; occupancy colors make it scannable while adjacent or stacked details explain the selected record. New screens inherit this established grammar rather than introducing a separate visual concept or imagery.

**Key Characteristics:**
- Navy navigation and blue action emphasis.
- White bordered panels on a light canvas.
- Compact tables with readable numbers and explicit labels.
- Occupancy colors paired with status text.
- Desktop and tablet layouts with visible keyboard focus.

## Colors

### Primary

Brand blue marks primary actions, keyboard focus and selected stalls. Navy anchors navigation and headings. The deeper brand hover color and active navigation tint provide interaction feedback.

### Neutral

Canvas, surface and border separate workspace regions without decorative depth. Ink carries body content; muted text supports captions and secondary information. Subtle backgrounds distinguish table headers and hovered rows.

### Status

Occupied uses green, vacant gray, reserved amber, maintenance red and inactive dark gray with white text. Each status has a pale fill, darker text and a matching border where the component requires one. Notices reuse blue for information, green for success and red for errors.

**The Separate Status Rule.** Occupancy colors describe the stall; payment status remains separate and explicitly labeled.

## Typography

Segoe UI with Arial and sans-serif fallbacks is the preserved system font for headings, body copy and controls. It is an intentional continuity decision from the prototype. The hierarchy uses weight and size rather than a second display family.

Page headings use the headline token, increasing to 30px with a 36px line height at the desktop breakpoint. Section headings use title; routine UI copy and tables use body; labels use label; badges, table headings and supporting captions use caption. The browser body baseline remains 16px; most operational content explicitly uses the smaller body role. Table headers are semibold uppercase with 0.025em tracking. Table cells and headers use tabular numerals.

**The Readable Record Rule.** Keep table numbers aligned with tabular numerals and retain explicit field labels when space is constrained.

## Layout

At 1024px and wider, a 256px navy sidebar stays visible and the main workspace sits beside it. Below that breakpoint, a Menu button controls navigation. Main content has a 1600px maximum width, centered within the available workspace, with 20px padding increasing to 32px on desktop. Panels use 20px internal padding, increasing to 24px on desktop.

Toolbars wrap with 12px gaps. Payment fields become two columns at 768px. At 1280px, the map and details use a 1.7fr / 1fr layout with a minimum 300px details column and 20px gap; details are sticky with a 20px top offset. Below that width they stack. The map preserves minimum stall widths and scrolls horizontally inside its panel. Tables likewise scroll within their own containers.

**The Preserve the Record Rule.** Let wide tables and maps scroll locally instead of squeezing their contents until labels and amounts become unreadable.

## Elevation & Depth

The interface is flat at rest. White surfaces, fine borders and background changes establish separation; cards do not use decorative shadows. Blue rings identify hovered or selected stalls. Keyboard focus uses a 3px blue outline with a 3px offset. These state treatments carry interaction meaning rather than simulating raised material.

**The State Before Shadow Rule.** Use rings for interaction state and borders for panel boundaries.

## Shapes

Controls, navigation links and stall tiles share the control radius. Larger panels use the panel radius; badges use a fully rounded silhouette. Panels and fields have fine solid borders. Stall tiles retain a minimum height of 76px; buttons retain a minimum height of 44px.

## Components

### Buttons

Primary buttons use blue with white semibold text. Secondary buttons use pale blue with dark blue text; danger buttons use pale red with dark red text. Hover deepens the corresponding fill. Disabled buttons use half opacity and a waiting cursor. All retain the shared keyboard focus outline.

### Inputs / Fields

Inputs, selects and textareas use white backgrounds, a muted border, the control radius and the input padding token. Labels sit above fields; placeholder text is muted. Checkbox controls use the brand accent. Validation feedback appears in a red notice with an explicit error list.

### Navigation

The navy rail uses pale blue link text. Hover and current-page states use the active navigation tint and white text; the current page is additionally semibold and carries an accessible current-page attribute. The shell supplies a skip link and a labeled tablet menu control.

### Chips

Small semibold pills communicate compact text labels. Occupancy variants inherit status fills and text colors; the map legend repeats the same terms visible on each tile. Financial status is not inferred from the occupancy legend.

### Cards / Containers

White bordered panels group coherent tasks and records. They use the panel radius and responsive padding described in Layout. Notices use the smaller radius and tinted backgrounds; empty states use centered muted explanatory text.

### Tables

Tables fill their container, with a pale header band, compact uppercase headings, fine row dividers and a pale hover background. Header padding is 12px; cell padding is 14px vertically and 12px horizontally. Horizontal overflow belongs to the table wrapper.

### Market Grid

Each selectable stall shows its code and written occupancy status. Hover adds a blue ring; selection adds an offset ring and an accessible pressed state. The details region announces updates politely. Where motion is permitted, stall rings transition over 150ms and loaded details enter over 180ms with a 6px horizontal translation. Reduced-motion preference removes these effects.

## Do's and Don'ts

### Do:
- Do preserve the prototype's navy navigation, blue actions and light bordered workspace.
- Do retain Segoe UI / Arial system typography and the compact readable type hierarchy.
- Do pair occupancy colors with written status and keep financial status separate.
- Do maintain visible keyboard focus, explicit labels and local overflow for wide records.

### Don't:
- Don't replace the established interface with a new visual concept or decorative imagery.
- Don't use occupancy color as evidence of payment status.
- Don't compress table content or map labels to avoid horizontal scrolling.
- Don't add decorative card shadows to the flat panel system.

Navigation refinement (2026-11-17): preserve navy/blue identity, add compact uppercase category headings, 44px navigation targets, fixed brand/account areas and a scrolling menu. Active item uses #2b5888 with an inset #82b5ff accent. Desktop icon rail is 4.75rem, expanded width 16rem; smaller screens use a 19rem maximum drawer with dim backdrop. See docs/architecture/NAVIGATION.md.
