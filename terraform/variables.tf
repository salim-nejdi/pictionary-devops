variable "image_name" {
  description = "Nom de l'image systeme utilisee pour la VM (catalogue OVH)"
  type        = string
  default     = "Debian 12"
}

variable "instance_flavor" {
  description = "Gabarit (flavor) definissant CPU/RAM/disque"
  type        = string
  default     = "b3-8"
}

variable "ssh_key_pair" {
  description = "Nom de la paire de cles SSH enregistree dans OpenStack"
  type        = string
  default     = "rudy"
}

variable "network_name" {
  description = "Reseau OpenStack auquel rattacher la VM (Ext-Net = IP publique OVH)"
  type        = string
  default     = "Ext-Net"
}

variable "allowed_tcp_ports" {
  description = "Ports TCP autorises en entree : 22 SSH, 80 HTTP, 443 HTTPS, 6443 API K3s"
  type        = list(number)
  default     = [22, 80, 443, 6443]
}
variable "region" {
  description = "Region OpenStack OVH (nom exact du catalogue)"
  type        = string
  default     = "BHS5"
}
