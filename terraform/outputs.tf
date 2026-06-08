# IP publique connue seulement apres creation : recuperee par Ansible pour le SSH
output "instance_ip" {
  description = "Adresse IP publique de la VM (utilisee par Ansible)"
  value       = openstack_compute_instance_v2.k3s_node.access_ip_v4
}

output "instance_name" {
  description = "Nom de la VM deployee"
  value       = openstack_compute_instance_v2.k3s_node.name
}

output "security_group_name" {
  description = "Nom du security group applique a la VM"
  value       = openstack_networking_secgroup_v2.k3s.name
}
