# OneID 2.12.1 — User MFA Production Approval Policy

## Scope

This patch aligns production approval with the approved MR1 risk boundary.
Routine mode changes and Emergency Bypass up to two hours require one
Administrator with fresh `SECURITY_CONFIGURATION_CHANGE` Admin Step-Up.

Emergency Bypass for four or eight hours continues to require approval by a
different Administrator. Request payload digest, policy version binding,
environment binding, reason, reference and typed confirmation remain mandatory.

## Behaviour

| Operation | Production authorization |
|---|---|
| OFF, ENROLLMENT, PILOT_ENFORCED, ENFORCED | Fresh Admin Step-Up; immediate |
| Emergency Bypass 30, 60 or 120 minutes | Fresh Admin Step-Up; immediate |
| Emergency Bypass 240 or 480 minutes | Fresh Admin Step-Up plus second Administrator |

The patch does not change the active production mode during deployment.
