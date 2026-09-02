---
paths:
  - tests/Unit/Domains/Employee/Sections/SocialInsuranceSectionTest.php
---

# Sections

## SocialInsuranceSection unit tests only reach rules, not afterValidation
This test file builds a bare Validator::make(data, section->completionRules()) so it never runs the section's afterValidation() hook (future-date and end-before-start checks) and cannot test empty-when-disabled for histories. 5 tests fail for this pre-existing reason and are unrelated to section-field changes. To assert afterValidation you must go through a service/HTTP save path (which wraps validateData) instead of bare Validator::make. insurance_status is valid against the insurance_type FormOption group (values social_security/supplementary/private), so this test needs RefreshDatabase + seedFormOptions(['insurance_type']).
