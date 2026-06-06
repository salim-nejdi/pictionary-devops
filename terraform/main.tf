# roles/deploy/tasks/main.yml — Deploiement de l'application sur K3s.
# Copie les manifestes, cree le Secret depuis le Vault, applique le tout.

- name: Creer le dossier des manifestes sur la VM
  ansible.builtin.file:
    path: "{{ manifests_remote_dir }}"
    state: directory
    mode: "0755"
  become: true

- name: Copier les manifestes K8s sur la VM
  # Les manifestes (00-namespace, 02-mariadb, 03-web, 04-ingress) sont copies
  # depuis le repo vers la VM. On exclut le 01-secret.example (cree dynamiquement ci-dessous).
  ansible.builtin.copy:
    src: "{{ item }}"
    dest: "{{ manifests_remote_dir }}/"
    mode: "0644"
  become: true
  loop:
    - "{{ playbook_dir }}/../kubernetes/00-namespace.yaml"
    - "{{ playbook_dir }}/../kubernetes/02-mariadb.yaml"
    - "{{ playbook_dir }}/../kubernetes/03-web.yaml"
    - "{{ playbook_dir }}/../kubernetes/04-ingress.yaml"

- name: Appliquer le namespace en premier
  ansible.builtin.shell: "k3s kubectl apply -f {{ manifests_remote_dir }}/00-namespace.yaml"
  become: true

- name: Creer/mettre a jour le Secret K8s depuis les valeurs du Vault
  # On genere le Secret via kubectl plutot que via un fichier YAML, pour ne jamais
  # ecrire les valeurs en clair sur le disque. --dry-run + apply = idempotent.
  ansible.builtin.shell: |
    k3s kubectl create secret generic pictionary-db-secret \
      --namespace {{ k8s_namespace }} \
      --from-literal=MYSQL_ROOT_PASSWORD='{{ vault_mysql_root_password }}' \
      --from-literal=MYSQL_DATABASE='{{ vault_mysql_database }}' \
      --from-literal=MYSQL_USER='{{ vault_mysql_user }}' \
      --from-literal=MYSQL_PASSWORD='{{ vault_mysql_password }}' \
      --dry-run=client -o yaml | k3s kubectl apply -f -
  become: true
  no_log: true                    # ne PAS afficher les secrets dans les logs

- name: Injecter la reference d'image immuable dans le manifeste web
  # __IMAGE_REF__ = image complete testee en preprod, promue telle quelle (jamais recompilee).
  ansible.builtin.replace:
    path: "{{ manifests_remote_dir }}/03-web.yaml"
    regexp: '__IMAGE_REF__'
    replace: "{{ image_ref }}"
  become: true

- name: Injecter la version affichee (runtime) dans le manifeste web
  # __APP_VERSION__ = version lisible affichee par le badge, independante de l'image.
  ansible.builtin.replace:
    path: "{{ manifests_remote_dir }}/03-web.yaml"
    regexp: '__APP_VERSION__'
    replace: "{{ app_version | default('dev') }}"
  become: true

- name: Appliquer les manifestes applicatifs (mariadb, web, ingress)
  ansible.builtin.shell: |
    k3s kubectl apply -f {{ manifests_remote_dir }}/02-mariadb.yaml
    k3s kubectl apply -f {{ manifests_remote_dir }}/03-web.yaml
    k3s kubectl apply -f {{ manifests_remote_dir }}/04-ingress.yaml
  become: true

- name: Attendre que le deploiement web soit pret (rollout)
  ansible.builtin.shell: |
    k3s kubectl rollout status deployment/pictionary-web -n {{ k8s_namespace }} --timeout=180s
  become: true
  changed_when: false
