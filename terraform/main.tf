locals {
  # "pictionary-prod" -> "prod" / "pictionary-preprod" -> "preprod"
  env_suffix = replace(terraform.workspace, "pictionary-", "")
}

# Pare-feu : OpenStack bloque tout en entree par defaut, on ouvre explicitement
resource "openstack_networking_secgroup_v2" "k3s" {
  name        = "pictionary-${local.env_suffix}-sg"
  description = "Security group noeud K3s Pictionary (${local.env_suffix})"
}

# Une regle ingress par port de var.allowed_tcp_ports (boucle for_each = DRY)
resource "openstack_networking_secgroup_rule_v2" "ingress_tcp" {
  for_each = toset([for port in var.allowed_tcp_ports : tostring(port)])

  direction         = "ingress"
  ethertype         = "IPv4"
  protocol          = "tcp"
  port_range_min    = each.value
  port_range_max    = each.value
  remote_ip_prefix  = "0.0.0.0/0"
  security_group_id = openstack_networking_secgroup_v2.k3s.id
  description       = "Autorise le port TCP ${each.value} en entree"
}

resource "openstack_compute_instance_v2" "k3s_node" {
  name        = "pictionary-${local.env_suffix}-node-1"
  image_name  = var.image_name
  flavor_name = var.instance_flavor
  key_pair    = var.ssh_key_pair

  security_groups = [openstack_networking_secgroup_v2.k3s.name]

  network {
    name = var.network_name
  }
}
