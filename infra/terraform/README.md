# Terraform

Infrastructure as code for staging and production environments
(`docs/delivery/15-deployment-strategy.md`).

**Empty until real cloud infrastructure is provisioned.** Writing Terraform
for infrastructure that does not exist and has no target cloud account would
be exactly the kind of fake implementation the charter forbids. This lands
when Phase 0's deployment path (staging/preview environments) is built --
tracked as the CI/CD task alongside this scaffold.

Local development uses `infra/docker/` instead, which is real and populated.
