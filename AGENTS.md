# Rules for AI Agents

## Core Principle: Code Encapsulation & Modularity

1. **File Size Limit (500 Lines):** 
   No single code file should exceed approximately 500 lines of code. This is a strict threshold to prevent codebase saturation.
   
2. **Encapsulate by Functionality:**
   If a file approaches the 500-line limit or handles more than one distinct domain of functionality, it **MUST** be split into smaller, logical, functional pieces.
   
3. **Single Responsibility:**
   Each file should have a single responsibility. For example:
   - UI interactions belong in a UI script (e.g., `waveform-ui.js`).
   - Pure logic and data manipulation belong in a logic script (e.g., `waveform-logic.js`).
   - AJAX routing belongs in dedicated endpoints (e.g., `includes/ajax-*.php`).
   
4. **Maintain Existing Structure:**
   Always adhere to the modular structure outlined in the `README.md`. Do not regress to monolithic designs (e.g., dumping all CSS into `style.css` or all JS into `script.js`).

**Remember:** Compact, well-named, and highly focused files lead to a sustainable and easily debuggable codebase.
