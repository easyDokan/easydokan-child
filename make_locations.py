import urllib.request
import json
import os

try:
    with urllib.request.urlopen("https://raw.githubusercontent.com/nuhil/bangladesh-geocode/master/divisions/divisions.json") as url:
        divs = json.loads(url.read().decode())[2]['data']
    
    with urllib.request.urlopen("https://raw.githubusercontent.com/nuhil/bangladesh-geocode/master/districts/districts.json") as url:
        dists = json.loads(url.read().decode())[2]['data']
        
    with urllib.request.urlopen("https://raw.githubusercontent.com/nuhil/bangladesh-geocode/master/upazilas/upazilas.json") as url:
        thanas = json.loads(url.read().decode())[2]['data']

    bd_map = {}
    
    for div in divs:
        div_name = div['name']
        bd_map[div_name] = {}
        for dist in dists:
            if dist['division_id'] == div['id']:
                dist_name = dist['name']
                bd_map[div_name][dist_name] = []
                for thana in thanas:
                    if thana['district_id'] == dist['id']:
                        bd_map[div_name][dist_name].append(thana['name'])
                        
    os.makedirs('assets/json', exist_ok=True)
    with open('assets/json/bd-locations.json', 'w') as f:
        json.dump(bd_map, f)
    print("Successfully generated assets/json/bd-locations.json")
except Exception as e:
    print(f"Error: {e}")
