terraform {
  required_version = ">= 1.6.0"

  required_providers {
    openstack = {
      source  = "terraform-provider-openstack/openstack"
      version = "~> 1.54" # branche 1.x uniquement, pas de saut majeur
    }
  }

  # State distant sur HCP Terraform : persistant entre les runs CI/CD + locking
  cloud {
    organization = "pictionary-devops"

    workspaces {
      # tags (et non name) pour cibler prod/preprod via TF_WORKSPACE sans editer ce fichier
      tags = ["pictionary"]
    }
  }
}

# Credentials fournis par les variables d'environnement OS_* (openrc.sh en local, secrets en CI)
provider "openstack" {}
