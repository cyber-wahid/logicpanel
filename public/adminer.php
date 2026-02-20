<?php
/**
 * LogicPanel Adminer - Standalone Version
 * No session, no token, no authentication wrapper
 * Simply includes the Adminer core
 * 
 * Note: Adminer handles its own sessions internally
 */

// Include Adminer core directly - it handles everything
include __DIR__ . '/adminer_core.php';